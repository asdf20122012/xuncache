package main

import (
	handle "./handle"
	"bufio"
	"encoding/json"
	"fmt"
	"net"
	"os"
	"path"
	"runtime"
	"strings"
	"time"
)

var store_int = make(map[string]uint64)                 //数字
var store_str = make(map[string]string)                 //字符串
var store_map = make(map[string]map[string]interface{}) //map(array)
var new_config = make(map[string]string)                //配置文件
var total_commands_processed uint64                     //总处理数
var err error

const (
	IP          string = "127.0.0.1"
	PORT        string = "1200"
	CONFIG_FILE string = "xuncache.conf"
	VERSION     string = "0.3"
)

func main() {
	runtime.GOMAXPROCS(runtime.NumCPU())
	//初始内容
	fmt.Printf("Server started, xuncache version %s\n", VERSION)
	//读取配置文件
	new_config = read_config()
	//创建服务端
	tcpAddr, err := net.ResolveTCPAddr("tcp4", new_config["bind"]+":"+new_config["port"])
	fmt.Printf("The server is now ready to accept connections on %s:%s\n", new_config["bind"], new_config["port"])
	checkError(err)
	listener, err := net.ListenTCP("tcp", tcpAddr)
	checkError(err)
	//输出状态
	go func() {
		for {
			//释放内存
			runtime.GC()
			//自身占用
			now_time := time.Now().Format("2006-01-02 15:04:05")
			map_num := len(store_map) + len(store_str) + len(store_int)
			fmt.Printf("[%s]DB keys: %d ,total_commands: %d\n", now_time, map_num, total_commands_processed)
			time.Sleep(2 * time.Second)
		}
	}()
	//数据处理
	for {
		conn, err := listener.Accept()
		if err != nil {
			continue
		}
		go handleClient(conn)
	}
}

//处理数据
func handleClient(conn net.Conn) {
	var back = make(map[string]interface{})
	var data_map = make(map[string]interface{})
	var data_str string
	//标记结束连接
	defer conn.Close()
	defer fmt.Print("Client closed connection\n")
	ipAddr := conn.RemoteAddr()
	fmt.Printf("Accepted %s\n", ipAddr)
	for {
		//获取数据
		var buf [1024]byte
		n, _ := conn.Read(buf[0:])
		b := []byte(buf[0:n])
		if len(b) < 1 {
			return
		}
		total_commands_processed++ //记录处理次数
		js, _ := handle.NewJson(b)
		pass, _ := js.Get("Pass").String()
		if pass != new_config["password"] && len(new_config["password"]) > 1 {
			fmt.Printf("Encountered a connection password is incorrect Accepted %s\n", ipAddr)
			back["error"] = true
			back["point"] = "password error!"
			rewrite(back, conn)
			return
		}
		//获取协议
		protocol, _ := js.Get("Protocol").String()
		//数据处理
		if protocol == `set` || protocol == `zset` {
			if protocol == `set` {
				data_str, err = js.Get("Data").String()
			} else {
				data_map, err = js.Get("Data").Map()
			}
			//数据判断
			if (data_map == nil || len(data_str) < 1) && (protocol == `set` || protocol == `lset`) {
				fmt.Print("There is no data \n")
				return
			}
		}

		//获取key
		key, _ := js.Get("Key").String()
		if len(key) < 1 && protocol != "info" {
			fmt.Printf("Error agreement is key %s\n", key)
			back["error"] = true
			back["point"] = "Please input Key!"
			rewrite(back, conn)
			return
		}

		//协议判断 处理
		switch protocol {
		case `set`:
			store_str[key] = data_str
			back["status"] = true
			break
		case `zset`:
			store_map[key] = data_map
			back["status"] = true
			break
		case `get`:
			back["data"] = store_str[key]
			back["status"] = true
			break
		case `zget`:
			back["data"] = store_map[key]
			back["status"] = true
			break
		case `delete`:
			delete(store_str, key)
			back["status"] = true
			break
		case `zdelete`:
			delete(store_map, key)
			back["status"] = true
			break
		case `incr`:
			store_int[key]++
			back["data"] = store_int[key]
			back["status"] = true
			break
		case `decr`:
			if store_int[key] == 0 {
				back["data"] = 0
				back["status"] = true
				break
			}
			store_int[key]--
			back["data"] = store_int[key]
			back["status"] = true
			break
		case `info`:
			var info = make(map[string]interface{})
			info["version"] = VERSION
			info["keys"] = len(store_map) + len(store_str) + len(store_int)
			info["total_commands"] = total_commands_processed
			back["data"] = info
			back["status"] = true
			break
		default:
			back["status"] = false
			fmt.Print("error protocol \n")
			break
		}
		//返回内容
		rewrite(back, conn)
	}
}

//读取配置文件
func read_config() (new_config map[string]string) {
	var config = make(map[string]string)
	dir, _ := path.Split(os.Args[0])
	os.Chdir(dir)
	path, _ := os.Getwd()
	config_file, err := os.Open(path + "/" + CONFIG_FILE) //打开文件
	defer config_file.Close()
	if err != nil {
		fmt.Println(err)
		fmt.Print("Can not read configuration file. now exit\n")
		os.Exit(0)
	}
	buff := bufio.NewReader(config_file) //读入缓存
	//读取配置文件
	for {
		line, err := buff.ReadString('\n') //以'\n'为结束符读入一行
		if err != nil {
			break
		}
		rs := []rune(line)
		if string(rs[0:1]) == `#` || len(line) < 3 {
			continue
		}
		str_type := string(rs[0:strings.Index(line, " ")])
		detail := string(rs[strings.Index(line, " ")+1 : len(rs)-1])
		config[str_type] = detail
	}
	//再次过滤 (防止没有配置文件)
	return verify(config)
}

//写入数据
func rewrite(back map[string]interface{}, conn net.Conn) {
	jsback, _ := json.Marshal(back)
	//返回内容
	conn.Write(jsback)
}

//验证配置文件
func verify(config map[string]string) (config_bak map[string]string) {
	if len(config["bind"]) < 3 {
		config["bind"] = IP
	}
	if len(config["port"]) < 1 {
		config["port"] = PORT
	}
	return config
}

//输出错误信息
func checkError(err error) {
	if err != nil {
		fmt.Fprintf(os.Stderr, "Fatal error: %s", err.Error())
		os.Exit(1)
	}
}
