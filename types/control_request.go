package types

//处理通信类型

//添加-保存类型
type Push struct {
	Key   string
	Index uint64
	Time  uint64
}

//查询key的单条数据
type Find struct {
	Key   string
	Index uint64
}

//范围查询 开始位置 结束位置 limit查询所有 order排序
type Query struct {
	Key         string
	Start       uint64
	Stop        uint64
	Limit       bool
	Order       bool
	Field_index string
}

//单一字段自增
type Incr struct {
	Key   string
	Index uint64
	Field string
}

//单一字段自减
type Decr struct {
	Key   string
	Index uint64
	Field string
}

//删除key的一条数据
type Del struct {
	Key   string
	Index uint64
}

//删除整个key
type Delete struct {
	Key string
}

//增加返回类型
type Back_Push struct {
	Errors bool
	Id     uint64
}

//自增 自减返回类型
type Back_Inc struct {
	Errors bool
	Nums   int
}
