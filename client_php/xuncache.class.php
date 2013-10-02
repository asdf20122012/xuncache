<?php

class xuncache {
	/**
     +------------------------------------------------------------------------------
     * xuncache模型类 + Litez Tcp协议
     +------------------------------------------------------------------------------
     * @author    sun8911879 <joijoi360@gmail.com>
     * @version   $Id: xuncache.class.php 2013-09-26 22:54:00 sun8911879 $
     +------------------------------------------------------------------------------
    */
	private $send_pack = array();
	private $send_over;
	private $tmp_buffio_cap = 128;

    //连接IP
    private $addr = "127.0.0.1";
    //连接端口
    private $port = "3351";
    //连接密码
    private $password = "13009120121";
    // 调试模式
    private $debug = true;
    // 查询表达式参数
    private $options = array();
	/**
     *----------------------------------------------------------
     * 架构函数
     * 取得TCP类的实例对象
     *----------------------------------------------------------
     * @param mixed $connection TCP连接信息
     *----------------------------------------------------------
     * @access public
     *----------------------------------------------------------
     */
    public function __construct() {
        $this->send_pack["v"] = 0.1;
		$this->send_pack["pact"] = "litez";
		$this->send_pack["type"] = "msg";
		$this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        $status = @socket_connect($this->socket,$this->addr,$this->port);
        if(!$status){
        	$this->customError("connect","Unable to connect to server");
        }
    }

    /**
     *----------------------------------------------------------
     * 利用__call方法实现一些特殊的Model方法
     *----------------------------------------------------------
     * @access public
     *----------------------------------------------------------
     * @param string $method 方法名称
     * @param array $args 调用参数
     *----------------------------------------------------------
     * @return mixed
     *----------------------------------------------------------
     */
    public function __call($method,$args) {
        $method = strtolower($method);
        // 连贯操作的实现
        if(in_array($method,array('key','index','time','hour','day','week','month','limit'),true)) {
            // 连贯操作的实现
            if(in_array($method,array("hour",'day','week','month','modern'),true)&&!$this->sval($args[0])){
                $this->options[$method] = "ture";
            }else{
                //获取列表--limit过滤
                if(in_array($method,array("limit"),true)){
                    $limit = explode(',', $args[0]);
                    if(!$limit[1]){
                        $this->options['start'] = 0;  
                        $this->options['end'] = ($limit[0] - 1);  
                    }else{
                        $this->options['start'] = $limit[0];  
                        $this->options['end'] = ($limit[1] - 1);  
                    }
                }
                $this->options[$method] =  $args[0]; 
            }
            return $this;
        }else{
            return false;
        }
    }

    /**
     *----------------------------------------------------------
     * 分析表达式
     *----------------------------------------------------------
     * @access proteced
     *----------------------------------------------------------
     * @param array $options 表达式参数
     *----------------------------------------------------------
     * @return array
     *----------------------------------------------------------
     */
    protected function _parseOptions($options=array()) {
        if(is_array($options))
            $options =  array_merge($this->options,$options);
        // 询过后清空表达式组装 避免影响下次查询查
        $this->options = array();
        // where条件分析
        if(@$options['time']){
            $time = $this->sval($options['time'])+time();
            return (int)$time;
        }elseif(@$options['hour']){
            $time = $this->sval($this->hour($options['hour']));
            return (int)$time;
        }elseif(@$options['day']){
            $time = $this->sval($this->day($options['day']));
            return (int)$time;
        }elseif(@$options['week']){
            $time = $this->sval($this->week($options['week']));
            return (int)$time;
        }elseif(@$options['month']){
            $time = $this->sval($this->month());
            return (int)$time;
        }
        return false;
    }
    /**
     *----------------------------------------------------------
     * 添加数据
     *----------------------------------------------------------
     * @access public
     *----------------------------------------------------------
     * @param mixed $options 表达式参数
     *----------------------------------------------------------
     * @return mixed
     *----------------------------------------------------------
     */
    public function add($array){
        $send['Pass'] = $this->password;
        $send['key'] = $this->options['key'];
        $send['index'] = $this->options['index'];
        $send['Protocol'] = "push";
        $send['expire'] = $this->_parseOptions($this->options);;
        $send['data'] = $array;
        $array = json_encode($send);
        $this->Write($array);
        $accept = $this->Read();
        $accept = json_decode($accept,true);
        //状态判断
        if(@$accept["Errors"] == true && $this->debug == true){
            $this->customError("connect",@$accept["Point"]);
        }elseif(@$accept["Errors"] == true){
            return false;
        }
        return @$accept["Id"];
    }

    /**
     *----------------------------------------------------------
     * 查找单条数据
     *----------------------------------------------------------
     * @access public
     *----------------------------------------------------------
     * @param mixed $options 表达式参数
     *----------------------------------------------------------
     * @return mixed
     *----------------------------------------------------------
     */
    public function find(){
        $send['Pass'] = $this->password;
        $send['key'] = $this->options['key'];
        $send['index'] = $this->options['index'];
        $send['index'] = $this->options['index'];
        $send['Protocol'] = "find";
        $array = json_encode($send);
        $this->Write($array);
        $accept = $this->Read();
        $accept = json_decode($accept,true);
        dump($accept);
        //状态判断
        if(@$accept["Errors"] == true && $this->debug == true){
            $this->customError("connect",@$accept["Point"]);
        }elseif(@$accept["Errors"] == true){
            return false;
        }
        return @$accept["Id"];
    }

    private function customError($errno, $errstr){ 
        echo "<b>Error:</b> [$errno] $errstr<br />";
        exit();
    }

    /**
     *----------------------------------------------------------
     * 时间过期计算--小时
     *----------------------------------------------------------
     * @access private
     *----------------------------------------------------------
     * @return mixed
     *----------------------------------------------------------
     */
    private function hour($num) {
        $year = date("Y");
        $month = date("m");
        $day = date("d");
        $hour = date("H");
        $mktime = mktime($hour+$num, 59, 59, $month, $day, $year);
        return $mktime;
    }

    /**
     *----------------------------------------------------------
     * 时间过期计算--当天24点
     *----------------------------------------------------------
     * @access private
     *----------------------------------------------------------
     * @return mixed
     *----------------------------------------------------------
     */
    private function day($num) {
        $year = date("Y");
        $month = date("m");
        $day = date("d");
        $mktime = mktime(23, 59, 59, $month, $day+$num, $year);
        return $mktime;
    }

    /**
     *----------------------------------------------------------
     * 时间过期计算--星期日24点
     *----------------------------------------------------------
     * @access private
     *----------------------------------------------------------
     * @return mixed
     *----------------------------------------------------------
     */
    private function week($num) {
        $year = date("Y");
        $month = date("m");
        $distance = date("w",time());//获取当前星期几
        $day = date("d");
        $day = ($day+(7-$distance))+($num*7);//计算天数
        $mktime = mktime(23, 59, 59, $month, $day, $year);
        return $mktime;
    }

    /**
     *----------------------------------------------------------
     * 时间过期计算--月末24点
     *----------------------------------------------------------
     * @access private
     *----------------------------------------------------------
     * @return mixed
     *----------------------------------------------------------
     */
    private function month() {
        $year = date("Y");
        $month = date("m");
        $day = date('d');
        $mktime = mktime(23, 59, 59, $month, $day, $year);
        return $mktime;
    }

    //过滤除数字外所有字符
    private function sval($num) {
      if (!preg_match("/^\d*$/", $num)) {return false;}
      $num = preg_replace('/\D/', '', $num);
      return $num;
    }

    //Litez
	private function Write($msg){
		$this->send_pack["len"] = strlen($msg);
		$send_pack = json_encode($this->send_pack).":EOF;";
		@socket_write($this->socket, $send_pack);
		@socket_write($this->socket, $msg);
	}

	private function Read(){
		$pack = $this->head_pack();
		if(!$pack){
			return false;
		}
		//判断协议
		if($pack["pact"] != $this->send_pack["pact"]){
			return false;
		}
		if($pack["v"] != $this->send_pack["v"]){
			return false;
		}
		if($pack["len"] < 1){
			return false;
		}
		//补全之前接收到byte
		$buffio_len = strlen($this->send_over);
		$buffio = null;
		$buffio = $this->send_over;
		$this->send_over = null;
		if($buffio_len >= $pack["len"]){
			return substr($buffio,0,$pack["len"]);
		}
		if($pack["len"] < $this->tmp_buffio_cap){
			$this->tmp_buffio_cap = $pack["len"];
		}
		while($head_pack_tmp = @socket_read($this->socket, $this->tmp_buffio_cap, PHP_BINARY_READ)){
			//读取追加字节
			$buffio .= $head_pack_tmp;
			if(strlen($buffio)>=$pack["len"]){
				//处理过长字符串
				$buffio_msg = substr($buffio,0,$pack["len"]);
				//冗余到下个通道
				$this->send_over = null;
				$this->send_over = substr($buffio,$pack["len"]);
				break;
			}
		}

		return $buffio_msg;
	}

	private function Close(){
		return socket_close($this->socket);
	}

	private function head_pack(){
		$buffio = null;
		//冗余上一次收到的byte
		$buffio = $this->send_over;
		$this->send_over = null;
		//头部协议获取
		while($head_pack_tmp = @socket_read($this->socket, 128, PHP_BINARY_READ)){
			//读取追加字节
			$buffio .= $head_pack_tmp;
			$pack_len = strpos($buffio,":EOF;");
			if($pack_len > 1){
				$head_pack = substr($buffio,0,$pack_len);
				$this->send_over = substr($buffio,$pack_len+5);
				break;
			}
		}
		if(!$head_pack){
			return false;
		}
		return json_decode($head_pack,true);

	}

}
    
    /**
     *----------------------------------------------------------
     * 浏览器友好的变量输出
     *----------------------------------------------------------
     * 此函数来自thinkphp
     *----------------------------------------------------------
     */
    function dump($var, $echo=true, $label=null, $strict=true) {
        $label = ($label === null) ? '' : rtrim($label) . ' ';
        if (!$strict) {
            if (ini_get('html_errors')) {
                $output = print_r($var, true);
                $output = "<pre>" . $label . htmlspecialchars($output, ENT_QUOTES) . "</pre>";
            } else {
                $output = $label . print_r($var, true);
            }
        } else {
            ob_start();
            var_dump($var);
            $output = ob_get_clean();
            if (!extension_loaded('xdebug')) {
                $output = preg_replace("/\]\=\>\n(\s+)/m", "] => ", $output);
                $output = '<pre>' . $label . htmlspecialchars($output, ENT_QUOTES) . '</pre>';
            }
        }
        if ($echo) {
            echo($output);
            return null;
        }else
            return $output;
    }
?>