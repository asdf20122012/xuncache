package util

import (
	"encoding/json"
	"errors"
	"net"
	"strings"
)

var (
	pack_len  int
	send_pack = make(map[string]interface{})
	data      interface{}
)

const (
	VERSION float64 = 0.1
	PACK    string  = "litez"
)

type Json_send struct {
	data interface{}
}

type Pack struct {
	send_genre   string
	accept_genre string
	conn         net.Conn
	over         []byte
}

func init() {
	//初始化发送协议
	send_pack["pact"] = PACK
	send_pack["v"] = VERSION
}

func NewTcp(conn net.Conn) *Pack {
	Tcpconn := &Pack{"", "", conn, nil}
	return Tcpconn
}

func (Tcpconn *Pack) Write(b []byte) error {
	//首先处理传输类型
	if Tcpconn.send_genre == "" {
		send_pack["type"] = "msg"
	} else {
		send_pack["type"] = Tcpconn.send_genre
	}
	//清空消息类型
	Tcpconn.send_genre = ""
	send_pack["len"] = len(b)
	//编码标头协议
	json_model := &Json_send{send_pack}
	josn_pack, _ := json_model.MarshalJSON()
	//发送数据
	_, err := Tcpconn.conn.Write(josn_pack)
	if err != nil {
		return err
	}
	_, eof_err := Tcpconn.conn.Write([]byte(`:EOF;`))
	if eof_err != nil {
		return eof_err
	}
	_, err_write := Tcpconn.conn.Write([]byte(b))
	if err_write != nil {
		return err_write
	}

	return nil
}

//读取数据
func (Tcpconn *Pack) Read() ([]byte, error) {
	head_pack, err := Tcpconn.head_pack()
	if err != nil {
		return nil, err
	}
	//校验协议
	if pact, ok := head_pack["pact"].(string); !ok || pact != PACK {
		return nil, errors.New("tcp pack error")
	}
	if v, ok := (head_pack["v"]).(float64); !ok || v != VERSION {
		return nil, errors.New("tcp pack version error")
	}
	if pack_len_tmp, ok := (head_pack["len"]).(float64); !ok || pack_len_tmp < float64(1) {
		return nil, errors.New("tcp pack len error")
	} else {
		pack_len = int(pack_len_tmp)
	}
	//获取传输类型
	if head_pack["type"] == nil {
		Tcpconn.accept_genre = "msg"
	} else {
		Tcpconn.accept_genre = head_pack["type"].(string)
	}
	//读取数据
	var buffio []byte
	var tmp_buffio_cap, start_num, i = 512, 0, 0
	//判断申请大小--防止过多获取
	if pack_len < tmp_buffio_cap {
		tmp_buffio_cap = pack_len
	}
	//补全之前接收到byte
	buffio = Tcpconn.over
	start_num = len(buffio)
	for {
		//判断协议长度
		if start_num >= pack_len || i >= 10 {
			break
		}
		if tmp_buffio_cap != pack_len-start_num {
			tmp_buffio_cap = pack_len - start_num
		}
		var tmp_buffio = make([]byte, tmp_buffio_cap)
		//接受byte
		n, read_err := Tcpconn.conn.Read(tmp_buffio[0:])
		if read_err != nil {
			break
		}
		//开始追加字节
		for _, slice := range tmp_buffio[0:n] {
			buffio = append(buffio, slice)
		}
		start_num = start_num + n
		i++
	}
	//超出内容放到下一部分接收
	Tcpconn.over = nil
	if len(buffio) > pack_len {
		for _, slice := range buffio[pack_len:] {
			Tcpconn.over = append(Tcpconn.over, slice)
		}
	}
	return buffio[0:pack_len], nil
}

//获取头部协议
func (Tcpconn *Pack) head_pack() (map[string]interface{}, error) {
	//获取数据
	var buffio []byte
	var buffio_len, i = 0, 0
	buffio = Tcpconn.over
	for {
		pack_len := strings.Index(string(buffio), ":EOF;")
		if pack_len > 0 || i >= 10 {
			break
		}
		var tmp_buffio [128]byte
		n, err := Tcpconn.conn.Read(tmp_buffio[0:])
		if err != nil {
			return nil, err
		}
		buffio_len = buffio_len + n
		//开始追加字节
		for _, slice := range tmp_buffio[0:n] {
			buffio = append(buffio, slice)
		}
		i++
	}

	//获取协议终止
	pack_len := strings.Index(string(buffio), ":EOF;")
	if pack_len < 1 {
		return nil, errors.New("tcp pack not proper")
	}
	buffio_pack := buffio[0:pack_len]
	//超出内容放到下一部分接收
	if pack_len+5 < len(buffio) {
		Tcpconn.over = nil
		for _, slice := range buffio[pack_len+5:] {
			Tcpconn.over = append(Tcpconn.over, slice)
		}
	}
	//解析标头协议
	err := json.Unmarshal(buffio_pack, &data)
	if err != nil {
		return nil, errors.New("tcp pack not proper")
	}
	pack, ok := (data).(map[string]interface{})
	if ok != true {
		return nil, errors.New("tcp pack not proper")
	}
	return pack, nil
}

//info信息类
//获取IP地址(去掉端口号)
func (Tcpconn *Pack) RemoteIp() string {
	ip_pack := strings.Split(Tcpconn.conn.RemoteAddr().String(), ":")
	return ip_pack[0]
}

// Implements the json.Marshaler interface.
func (json_model *Json_send) MarshalJSON() ([]byte, error) {
	return json.Marshal(&json_model.data)
}
