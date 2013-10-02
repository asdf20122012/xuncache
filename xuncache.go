package main

import (
	"errors"
	"fmt"
	"net"
	"os"
	"runtime"
	"time"
	"xuncache/core"
	"xuncache/util"
)

var (
	total_commands_processed uint64 //总处理数
	err                      error  //错误类型
)

//配置文件
type Server_info struct {
	IP        string
	Port      string
	File      string
	Save_time uint64
	Password  string
}

//默认常量(配置文件)
const (
	IP          string = ""
	PORT        string = "3351"
	CONFIG_FILE string = "xuncache.conf"
	VERSION     string = "0.4"
	FILE        string = "dump.mdb"
	MAX_TIME    uint64 = 1800
)

func init() {
	//初始内容
	fmt.Printf("Server started, xuncache version %s\n", VERSION)
}

func main() {
	runtime.GOMAXPROCS(runtime.NumCPU())
	//读取配置文件
	server := configure()
	//创建服务端
	tcpAddr, err := net.ResolveTCPAddr("tcp4", server.IP+":"+server.Port)
	fmt.Printf("The server is now ready to accept connections on %s:%s\n", server.IP, server.Port)
	checkError(err)
	listener, err := net.ListenTCP("tcp", tcpAddr)
	checkError(err)
	//数据处理
	for {
		conn, err := listener.Accept()
		if err != nil {
			continue
		}
		conn.SetDeadline(time.Now().Add(30 * time.Second))
		conn.SetReadDeadline(time.Now().Add(20 * time.Second))
		conn.SetWriteDeadline(time.Now().Add(10 * time.Second))
		go server.handleClient(conn)
	}
}

//处理数据
func (server *Server_info) handleClient(conn net.Conn) {
	//标记结束连接
	defer conn.Close()
	defer fmt.Print("Client closed connection\n")
	fmt.Printf("Accepted %s\n", conn.RemoteAddr())
	//创建对象
	Pack := util.NewTcp(conn)
	for {
		total_commands_processed++ //记录处理次数
		//读取数据
		json_pack, err := Pack.Read()
		if err != nil {
			break
		}
		//转换协议
		Receive, err := util.NewJson(json_pack)
		if err != nil {
			Pack.Write(core.Errors(err))
			break
		}
		//初始化
		sources := core.Init(Receive)
		if server.Password != sources.Password {
			Pack.Write(core.Errors(errors.New("Password error!")))
			return
		}
		Back := sources.Handle()
		Pack.Write(Back)
	}
}

func configure() (result *Server_info) {
	// 初始化参数
	/*
		file_path, _ := exec.LookPath(os.Args[0])
		Path, _ := filepath.Abs(file_path)
		Path = strings.Replace(Path, "xuncache", "", 1)
		config, err := util.ReadConfigFile(Path + "/" + CONFIG_FILE)
	*/
	config, err := util.ReadConfigFile("/Users/syx/code/go/src/xuncache/xuncache.conf")
	checkError(err)
	Server := &Server_info{}
	//server信息
	Server.IP, err = config.GetString("server", "bind")
	if err != nil {
		Server.IP = IP
	}
	Server.Port, err = config.GetString("server", "port")
	if err != nil {
		Server.Port = PORT
	}
	Server.File, err = config.GetString("server", "file")
	if err != nil {
		Server.File = FILE
	}
	Server.Password, _ = config.GetString("server", "password")
	return Server
}

//输出错误信息
func checkError(err error) {
	if err != nil {
		fmt.Fprintf(os.Stderr, "Fatal error: %s\n", err.Error())
		os.Exit(1)
	}
}
