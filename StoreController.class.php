<?php
/**
 * tpshop
 * ============================================================================
 * * 版权所有 2015-2027 淘宝搜索店铺甜心100，并保留所有权利。
 * 网站地址: http://www.we10.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: IT宇宙人 2015-08-10 $
 */ 
namespace WXAPI\Controller;
use WXAPI\Logic\GoodsLogic;
use WXAPI\Logic\StoreLogic;
use Think\Controller;
use Think\Page;

class StoreController extends BaseController {
    

    public function _initialize(){
        $store_id = I('store_id',1);
        $this->store = M('store')->where(array('store_id'=>$store_id))->find();
    }
    
    public function getStoreClass(){
		
    	$store_class = M('store_class')->where("is_show=1")->order("sc_sort asc")->select();
		foreach($store_class as $k=>$v) {
			if($v['sc_image']){
				$store_class[$k]['sc_image'] = SITE_URL.$v['sc_image'];
			}
		}
    	exit(json_encode($store_class));
    }
    
    
    public function getStores(){
		
		$size = 10;
		$page = I('page');
		if ($page < 1) {
			$page = 1;
		}
		$term = I('term');
		$map = '1';
		$keyWord = I('keyWord');
		if (!empty($keyWord)) {	
			$map = array('store_name'=>array('like', "%$keyWord%"));
		}
			$store_class = M('store')->alias('s')->field('s.*, c.sc_name')
			->join('ty_store_class c ON c.sc_id=s.sc_id')
			->where(array("s.sc_id"=>$_GET['cid'],"store_state"=>1))
			->where($map)
			->select();
		if ($term == 'sales') {
				$store_class = M('store')->alias('s')->field('s.*, c.sc_name')
				->join('ty_store_class c ON c.sc_id=s.sc_id')
				->where(array("s.sc_id"=>$_GET['cid'],"store_state"=>1))
				->where($map)
				->order('store_sales desc')
				->select();
		}
		$distance = M('config')->where(array('inc_type'=>'basic', 'name'=>'distance'))->getField('value');
			foreach ($store_class as $k=>$v){
				
				//查询评价
				$store_id=$v['store_id'];
				$service_rank=M('')->query(" select AVG(service_rank) as service_rank from __PREFIX__comment where store_id = $store_id ");
				$deliver_rank=M('')->query(" select AVG(deliver_rank) as deliver_rank from __PREFIX__comment where store_id = $store_id ");
				$goods_rank=M('')->query(" select AVG(goods_rank) as goods_rank from __PREFIX__comment where store_id = $store_id ");
				if(empty($service_rank[0]['service_rank'])){
					$service_rank[0]['service_rank']=5.0;
				}
				if(empty($deliver_rank[0]['deliver_rank'])){
					$deliver_rank[0]['deliver_rank']=5.0;
				}
				if(empty($goods_rank[0]['goods_rank'])){
					$goods_rank[0]['goods_rank']=5.0;
				}				
				$store_class[$k]['store_servicecredit'] =$service_rank[0]['service_rank'];
				$store_class[$k]['store_deliverycredit'] = $deliver_rank[0]['deliver_rank'];
				$store_class[$k]['store_desccredit'] = $goods_rank[0]['goods_rank'];
				$store_class[$k]['store_logo'] = SITE_URL.$v['store_logo'];
				$store_class[$k]['goods_num'] = M('goods')->where(array("store_id"=>$v['store_id'],"is_on_sale"=>1,"is_delete"=>0))->count();
				$store_class[$k]['goods'] = M('goods')->where(array("store_id"=>$v['store_id'],"is_on_sale"=>1,"is_delete"=>0))->limit('0 , 3')->select();
				foreach ($store_class[$k]['goods'] as $key=>$value){
					$store_class[$k]['goods'][$key]['original_img'] = SITE_URL.$value['original_img'];
				}
				//计算距离跟时间
				$dest = $this->getDistance($v['la'], $v['lo'], $_GET['la'], $_GET['lo']);
				$store_class[$k]['dest'] = sprintf("%.2f",$dest / 1000) .'km';
				$store_class[$k]['time'] = (int)($dest / 1000 + 0.5) * $v[speed] + $v[goods_init];
				if ($distance < $dest/1000)
				{
						unset($store_class[$k]);
						continue;
				}
			}
		if ($term == 'dest') {
			array_multisort(array_column($store_class,'dest'),SORT_ASC,$store_class);
		}
    	exit(json_encode($store_class));
    }
    public function getStoresnormal(){
    	$store_class = M('store')->where(array("sc_id"=>$_GET['cid'],"store_state"=>1))->select();
    	foreach ($store_class as $k=>$v){
    		$store_class[$k]['store_logo'] = SITE_URL.$v['store_logo'];
    		$store_class[$k]['goods_num'] = M('goods')->where(array("store_id"=>$v['store_id'],"is_on_sale"=>1,"is_delete"=>0))->count();
    		$store_class[$k]['goods'] = M('goods')->where(array("store_id"=>$v['store_id'],"is_on_sale"=>1,"is_delete"=>0))->limit('0 , 4')->select();
    		foreach ($store_class[$k]['goods'] as $key=>$value){
    			$store_class[$k]['goods'][$key]['original_img'] = SITE_URL.$value['original_img'];
    		}
    		
    	}
    	exit(json_encode($store_class));
    }    
    /***
     * 获取店铺信息
     */
    public function store_info(){
    
        $store_id = I('store_id',1);
        $store = M('store')->where("store_id=$store_id")->find();
		$store['sc_name'] = M('store_class')->where(array('sc_id'=>$store['sc_id']))->getField('sc_name');
		$store['store_logo'] = SITE_URL.$store['store_logo'];
		//计算距离跟时间
		$dest = $this->getDistance($store['la'], $store['lo'], $_GET['la'], $_GET['lo']);
		$store['dest'] = sprintf("%.2f",$dest / 1000) .'km';
		$store['time'] = (int)($dest / 1000 + 0.5) * $store[speed] + $store[goods_init];
		//获取商铺幻灯片
		$store_slide= explode(',', $store['store_slide']);
		$store_slide_url = explode(',', $store['store_slide_url']);
        $store['sales'] = M('goods')->where(array('store_id'=>$store_id,"is_on_sale"=>1,"is_delete"=>0))->getField('sum(sales_sum)');
        // $sales = M('goods')->where(array('store'=>$store_id))->getField('sum(sales_sum)');
		$newstore_slide=array();
		foreach($store_slide as $k=>$v) {				
				if($v){					
					$newstore_slide[$k]['images'] = SITE_URL.$v;
					$newstore_slide[$k]['ad_link'] = $store_slide_url[$k];					
				}
		}
		$store['store_slide']=$newstore_slide;
		
		$json_arr = array('status'=>1,'msg'=>'获取成功','result'=>$store );
		exit(json_encode($json_arr));
    }
    
    /**
     * 关于店铺(店铺基本信息)
     */
    public function about(){
        $store_id = I('store_id',1); // 当前分类id //  "store_id , store_name , grade_id , province_id , city_id , store_address , store_time"
        $store = M('store')->where("store_id=$store_id")->find();

        $province_id = $store['province_id']; 
        $city_id = $store['city_id'];

        //所在地
        $regions = M("region")->where(" id in( ".$store['province_id'] ." , ".$store['city_id']." , ".$store['district']." )")->select();
        $region= "";
        foreach($regions as $k => $v){
            $region .= $v['name'];
        }
        $store['location'] = $region;
         
        $gradgeId = $store['grade_id'];
        
        //查询店铺等级
        $gradgeName = M('store_grade')->where("sg_id = $gradgeId")->getField("sg_name");
        $store['grade_name'] = $gradgeName;
        
        $json_arr = array('status'=>1,'msg'=>'获取成功','result'=>$store );
        
        exit(json_encode($json_arr));
    }
      

    /***
     * 店铺
     */
    public function index(){
        
        $store_id = I('store_id',1);
        $store = M('store')->where("store_id=$store_id")->find();

        //新品
        $new_goods = M('goods')->field('goods_content',true)->where(array('store_id'=>$store_id,'is_new'=>1,"is_on_sale"=>1,"is_delete"=>0))->order('goods_id desc')->limit(10)->select();
        //推荐商品
        $recomend_goods = M('goods')->field('goods_content',true)->where(array('store_id'=>$store_id,'is_recommend'=>1,"is_on_sale"=>1,"is_delete"=>0))->order('goods_id desc')->limit(10)->select();  
        //热卖商品
        $hot_goods = M('goods')->field('goods_content',true)->where(array('store_id'=>$store_id,'is_hot'=>1,"is_on_sale"=>1,"is_delete"=>0))->order('goods_id desc')->limit(10)->select();
        
        //店铺商品总数
        $storeCount =  M('goods')->where("store_id=".$store_id." and is_on_sale=1 and is_delete=0")->sum('store_count');
        
        $store['new_goods'] = $new_goods;
        $store['recomend_goods'] = $recomend_goods;
        $store['hot_goods'] = $hot_goods;
        $store['store_count'] = $storeCount;
        
        $json_arr = array('status'=>1,'msg'=>'获取成功','result'=>$store );
        
        exit(json_encode($json_arr));
    }
    
    
    /**
     * 搜索店铺内的商品
     */
    public function searchStoreGoodsClass(){
    
        $store_id = I('store_id',1);
      
        $search_key = I("search_key");  // 关键词搜索
        
        $where = " where 1 = 1 ";
        $orderby =I('orderby','goods_id'); // 排序
        $orderdesc = I('orderdesc','desc'); // 升序 降序
    
        $search_key && $where .= " and (goods_name like '%$search_key%' or keywords like '%$search_key%')";
    
        if($store_id > 0){
            $where .= " and store_id = ".  $store_id;     //店铺ID
        }
        
        $cat_id  = I("cat_id",0); // 所选择的商品分类id
        if($cat_id > 0)
        {
            $where .= " and store_cat_id2 = ".  $cat_id ; // 初始化搜索条件
        }
        
        $Model  = new \Think\Model();
        $limit = " limit 1";
        
        $list = M("goods")->where("store_id = 1,is_on_sale=1 and is_delete=0")->field("goods_remark,goods_content" , true)->limit(0 , 10)->select();// ->query("select *  from __PREFIX__goods $where $limit ");
        
        /*
        $result = $Model->query("select count(1) as count from __PREFIX__goods $where ");
        
        $count = $result[0]['count'];
        
        $_GET['p'] = $_REQUEST['p'];
        
        $page = new Page($count,10);
       
        $order = " order by $orderby $orderdesc "; // 排序
        $limit = " limit ".$page->firstRow.','.$page->listRows;
        $list = $Model->query("select *  from __PREFIX__goods $where $order $limit"); */
    
        $json_arr = array('status'=>1,'msg'=>'获取成功','result'=>$list );
        $json_str = json_encode($json_arr);
        
        exit(json_encode($json_arr));
    }
    
    /**
     * 获取店铺商品分类
     */
    public function storeGoodsClass(){
        $store_id = $this->store['store_id'];
        $goods_logic = new GoodsLogic();
        $store_goods_class =  $goods_logic->getStoreGoodsClass($store_id);
        $json_arr = array('status'=>1,'msg'=>'获取成功','result'=>$store_goods_class);
        exit(json_encode($json_arr));
    }

    /**
     * @author dyr
     * 修改于2016/08/26
     * 获取店铺商品列表
     */
    public function storeGoods()
    {
        
        
        C('URL_MODEL',0); // 返回给手机app 生成路径格式 为 普通 index.php?=api&c=  最普通的路径格式
        
        $store_id = $_GET['store_id'];
        $key = I('get.key','');;
        
        
        
        $p = I('get.p',0); // 当前分类id
        $filter_param = array(); // 帅选数组
        
        $brand_id = I('get.brand_id',0);
        $spec = I('get.spec',0); // 规格
        $attr = I('get.attr',''); // 属性
        $sort = I('get.sort','goods_id'); // 排序
        $sort_asc = I('get.sort_asc','asc'); // 排序
        $price = I('get.price',''); // 价钱
        $start_price = trim(I('post.start_price','0')); // 输入框价钱
        $end_price = trim(I('post.end_price','0')); // 输入框价钱
        if($start_price && $end_price) $price = $start_price.'-'.$end_price; // 如果输入框有价钱 则使用输入框的价钱
        
        $brand_id  && ($filter_param['brand_id'] = $brand_id); //加入帅选条件中
        $spec  && ($filter_param['spec'] = $spec); //加入帅选条件中
        $attr  && ($filter_param['attr'] = $attr); //加入帅选条件中
        $price  && ($filter_param['price'] = $price); //加入帅选条件中
         
        $goodsLogic = new \Home\Logic\GoodsLogic(); // 前台商品操作逻辑类
        
        if($key == "")
        $filter_goods_id = M('goods')->where(array("is_on_sale"=>1,'store_id'=>$store_id,"is_delete"=>0))->cache(true)->getField("goods_id",true);
         else{
         	$filter_goods_id = M('goods')->where(array("is_on_sale"=>1,'store_id'=>$store_id,"goods_name"=>array("like","%$key%")))->cache(true)->getField("goods_id",true);
         	 
         }
        // 过滤帅选的结果集里面找商品
        if($brand_id || $price)// 品牌或者价格
        {
        	$goods_id_1 = $goodsLogic->getGoodsIdByBrandPrice($brand_id,$price); // 根据 品牌 或者 价格范围 查找所有商品id
        	$filter_goods_id = array_intersect($filter_goods_id,$goods_id_1); // 获取多个帅选条件的结果 的交集
        }
        if($spec)// 规格
        {
        	$goods_id_2 = $goodsLogic->getGoodsIdBySpec($spec); // 根据 规格 查找当所有商品id
        	$filter_goods_id = array_intersect($filter_goods_id,$goods_id_2); // 获取多个帅选条件的结果 的交集
        }
        if($attr)// 属性
        {
        	$goods_id_3 = $goodsLogic->getGoodsIdByAttr($attr); // 根据 规格 查找当所有商品id
        	$filter_goods_id = array_intersect($filter_goods_id,$goods_id_3); // 获取多个帅选条件的结果 的交集
        }
        
        $filter_menu  = $goodsLogic->get_filter_menu($filter_param,'goodsList'); // 获取显示的帅选菜单
        $filter_price = $goodsLogic->get_filter_price($filter_goods_id,$filter_param,'goodsList'); // 帅选的价格期间
        $filter_brand = $goodsLogic->get_filter_brand($filter_goods_id,$filter_param,'goodsList',1); // 获取指定分类下的帅选品牌
        $filter_spec  = $goodsLogic->get_filter_spec($filter_goods_id,$filter_param,'goodsList',1); // 获取指定分类下的帅选规格
        $filter_attr  = $goodsLogic->get_filter_attr($filter_goods_id,$filter_param,'goodsList',1); // 获取指定分类下的帅选属性
         
        $count = count($filter_goods_id);
        $page = new Page($count,5);
        $page->firstRow = $p * $page->listRows;
        if($count > 0)
        {
        	$goods_list = M('goods')->field('goods_id,cat_id2,goods_sn,goods_name,shop_price,comment_count,original_img,sales_sum')->where("is_on_sale=1 and is_delete=0  and   goods_id in (".  implode(',', $filter_goods_id).")")->order("$sort $sort_asc")->limit($page->firstRow.','.$page->listRows)->select();
        	$filter_goods_id2 = get_arr_column($goods_list, 'goods_id');
        }
        $goods_category = M('goods_category')->where('is_show=1')->cache(true)->getField('id,name,parent_id,level'); // 键值分类数组
         
        foreach ($goods_list as $key=>$value)
        {
        	$goods_list[$key]['image'] = SITE_URL.$value['original_img'];
        }
         
        $list['goods_list'] = $goods_list;
        $i = 1;
        //菜单
        foreach($filter_menu as $k => $v) // 依照app端的要求 去掉 键名
        {
        	$v['name'] = $v['text'];
        	unset($v['text']);
        	$list['filter_menu'][] = $v;  // 帅选规格
        }
        
        // 规格
        foreach($filter_spec as $k => $v) // 依照app端的要求 去掉 键名
        {
        	$items['name'] = $v['name'];
        	foreach($v['item'] as $k2 => $v2)
        	{
        		$items['item'][] = array('name'=>$v2['item'],'href'=>$v2['href'],'id'=>$i++);
        	}
        	$list['filter_spec'][] = $items;
        	$items = array();
        }
        
        // $list['filter_spec'] = $filter_spec;
        // 属性
        foreach($filter_attr as $k => $v) // 依照app端的要求 去掉 键名
        {
        	$items['name'] = $v['attr_name'];
        	foreach($v['attr_value'] as $k2 => $v2)
        	{
        		$items['item'][] = array('name'=>$v2['attr_value'],'href'=>$v2['href'],'id'=>$i++);
        	}
        	 
        	$list['filter_attr'][] = $items;
        	$items = array();
        }
        // 品牌
        foreach($filter_brand as $k => $v) // 依照app端的要求 去掉 键名
        {
        	$list['filter_brand'][] = array('name'=>$v['name'],'hreg'=>$v['href'],'id'=>$i++);
        }
        
        // 价格
        foreach($filter_price as $k => $v) // 依照app端的要求 去掉 键名
        {
        	$list['filter_price'][] = array('name'=>$v['value'],'href'=>$v['href'],'id'=>$i++);
        }
        
        $list['sort'] =  $sort;
        $list['sort_asc'] =  $sort_asc;
        $sort_asc = $sort_asc == 'asc' ? 'desc' : 'asc';
        $list['orderby_default'] = urldecode(U("Goods/goodsList",$filter_param,'')); // 默认排序
        $list['orderby_sales_sum'] = urldecode(U("Goods/goodsList",array_merge($filter_param,array('sort'=>'sales_sum','sort_asc'=>'desc')),'')); // 销量排序
        $list['orderby_price'] = urldecode(U("Goods/goodsList",array_merge($filter_param,array('sort'=>'shop_price','sort_asc'=>$sort_asc)),'')); // 价格
        $list['orderby_comment_count'] = urldecode(U("Goods/goodsList",array_merge($filter_param,array('sort'=>'comment_count','sort_asc'=>'desc')),'')); // 评论
        $list['orderby_is_new'] = urldecode(U("Goods/goodsList",array_merge($filter_param,array('sort'=>'is_new','sort_asc'=>'desc')),'')); // 新品
        C('TOKEN_ON',false);
        
        $json_arr = array('status'=>1,'msg'=>'获取成功','result'=>$list );
        $json_str = json_encode($json_arr,true);
        exit($json_str);
        
        
    }

    /**
     * @author dyr
     * 店铺收藏or取消操作
     */
    public function collectStoreOrNo()
    {
        $store_logic = new StoreLogic();
        $json_arr = $store_logic->collectStoreOrNo($this->user_id,$this->store['store_id']);
        exit(json_encode($json_arr));
    }
    
    //根据坐标计算距离
    function getDistance($lat1, $lng1, $lat2, $lng2){
    	$earthRadius = 6367000; //approximate radius of earth in meters
    	$lat1 = ($lat1 * pi() ) / 180;
    	$lng1 = ($lng1 * pi() ) / 180;
    	$lat2 = ($lat2 * pi() ) / 180;
    	$lng2 = ($lng2 * pi() ) / 180;
    	$calcLongitude = $lng2 - $lng1;
    	$calcLatitude = $lat2 - $lat1;
    	$stepOne = pow(sin($calcLatitude / 2), 2) + cos($lat1) * cos($lat2) * pow(sin($calcLongitude / 2), 2);
    	$stepTwo = 2 * asin(min(1, sqrt($stepOne)));
    	$calculatedDistance = $earthRadius * $stepTwo;
    	return round($calculatedDistance);
    }

    //判断是否添加过申请入住信息
    public function store_apply_judge() {
        $user_id = I('get.user_id');
		
		//判断是否已经是入驻店铺
		$res =  M('store_apply')->where(array('user_id'=>$user_id,'apply_state'=>0))->select();
		
        $is_store =  M('store')->where(array('user_id'=>$user_id))->select();
		if($is_store){
			
			exit(json_encode(array('status'=>1,'msg'=>'您已经是店家了')));
		}
        if ($res){
            exit(json_encode(array('status'=>1,'msg'=>'正在审核中')));
        }else{
            exit(json_encode(array('status'=>0,'msg'=>'未进行申请')));
        }
    }
	
	
	//商家登录资料显示页面
	public  function store_login_info(){
		
		$user_id = I('get.user_id');
		
		$res =  M('store_apply')->where(array('user_id'=>$user_id,'apply_state'=>1))->find();
		
		$host_url="https://".$_SERVER['SERVER_NAME'].'/index.php/Seller';
		
		$res['host_url']=$host_url;
		
		if($res){
		
			exit(json_encode(array('status'=>1,'data'=>$res)));
		}
		
	}

    //存储店铺申请信息
    public function store_apply_info() {
		
    	$user_id = I('get.user_id');
		$session_id = I('get.session_id');
		$data = json_decode($_REQUEST['data'], true);
		//获取时间配置
		$sms_time_out = M('config')->where(array('inc_type'=>'sms', 'name'=>'sms_time_out'))->getField('value');
		//检测验证码是否正确
    	$key = M('sms_log')
		->where(array('mobile' => $data['contacts_mobile'], 'code' => $data['code'], 'add_time' => array('gt', time()-$sms_time_out), 'session_id' => $session_id))
		->find();
		if (!empty($key)) {
			$data['user_id'] = $user_id;
			$data['add_time'] = time();
			$k = M('store_apply')->add($data);
			if ($k) {
				exit(json_encode(array('status'=>1,'msg'=>'申请成功')));
			} else {
				exit(json_encode(array('status'=>-1,'msg'=>'申请失败')));
			}
		} else {
			exit(json_encode(array('status'=>-1,'msg'=>'验证码输入错误')));
		}
    	
    }
    
    //获取商家入驻协议
    public function get_enter_agreement() {
    	$article = M('article')->field('a.*, c.cat_name')->alias('a')
		->join('ty_article_cat c ON c.cat_id=a.cat_id')
		->where(array('cat_name'=>'商家入驻协议'))
		->find();
	exit(json_encode(array('title'=>$article['title'], 'content'=>$article['content'])));
    }

     //获取商品列表
    public function getGoodsList() {

        //M('test')->add(array('an'=>1));

        $store_id = $_GET['id'];
   
        $sort = I('sort');
        $sort_type = I('sort_type');
        $class1 = I('class1');
        $keyWord = I('keyWord');
        $where = array();
        if (!empty($keyWord)) { 
            $where = array('goods_name'=>array('like', "%$keyWord%"));
            $goods_list = M('goods')->where(array('store_id'=>$store_id,"is_on_sale"=>1,"is_delete"=>0))->where($where)->select();
        }else{
            if($sort > -1){
                switch($sort){
                    case 0:
                    if($sort_type == 0){
                        $order = 'shop_price asc';
                    }else{
                        $order = 'shop_price desc';
                    }
                    break;
                    case 1:
                    if($sort_type == 0){
                        $order = 'sales_sum asc';
                    }else{
                        $order = 'sales_sum desc';
                    }
                    break;
                    case 2:
                    if($sort_type == 0){
                        $order = 'last_update asc';
                    }else{
                        $order = 'last_update desc';
                    }
                    break;
                    case 3:
                    if($sort_type == 0){
                        $order = 'comment_count asc';
                    }else{
                        $order = 'comment_count desc';
                    }
                    break;
                }
                if($class1 > 0){
                    $goods_list = M('goods')->where(array('store_id'=>$store_id,'store_cat_id1'=>$class1,"is_on_sale"=>1,"is_delete"=>0))->order($order)->select();
                }else{
                    $goods_list = M('goods')->where(array('store_id'=>$store_id,"is_on_sale"=>1,"is_delete"=>0))->order($order)->select();
                }
            }else{
                if($class1 > 0){
                    $goods_list = M('goods')->where(array('store_id'=>$store_id,'store_cat_id1'=>$class1,"is_on_sale"=>1,"is_delete"=>0))->select();
                }else{
                    $goods_list = M('goods')->where(array('store_id'=>$store_id,"is_on_sale"=>1,"is_delete"=>0))->select();
                }
            }
        }

        foreach($goods_list as $k=>$v) {
            $goods_list[$k]['original_img'] = SITE_URL.$v['original_img'];
            // 店铺商品优惠折扣显示 20181129 亮 START
            if (($v['prom_type'] == 3) && $v['prom_id']){
                $prom_goods = M('prom_goods')->where(array('id'=>$v['prom_id']))->find();
                $prom_code = SITE_URL.$prom_goods['prom_code'];
                $statusss = 1;
                $datass = time();
                if (($datass > $prom_goods['start_time']) && ($datass < $prom_goods['end_time'])){
                    if ($prom_goods['type'] == 0){
                        $prom_goods_price = $v['shop_price'] * $prom_goods['expression'] / 100;
                        $prom_goods_content = 0;
                    }elseif($prom_goods['type'] == 1){
                        $prom_goods_price = $v['shop_price'] - $prom_goods['expression'];
                        $prom_goods_content = 0;
                    }elseif($prom_goods['type'] == 2){
                        $prom_goods_price = $prom_goods['expression'];
                        $prom_goods_content = 0;
                    }else{
                        $prom_goods_price = $v['shop_price'];
                        $prom_goods_content = "购买赠送代金券$prom_goods[expression]元";
                    }
                }
                $goods_list[$k]['prom_goods_price'] = $prom_goods_price;
                $goods_list[$k]['prom_goods_content'] = $prom_goods_content;
                $goods_list[$k]['prom_code'] = $prom_code;
                $goods_list[$k]['status_status'] = $statusss;
            }
            // 店铺商品优惠折扣显示 20181129 亮 END

        }
        $json_arr = array('status'=>1,'msg'=>'获取成功','result'=>$goods_list );
        exit(json_encode($json_arr));
    }

    //获取商家所有一级分类和相关商品个数
    public function get_class_num(){
        $store_id = I('store_id');
        $res = M()->query("select count(*) as num,store_cat_id1 as id from ty_goods where store_id='$store_id'   and is_on_sale=1 and is_delete=0 and  store_cat_id1 in (select s.cat_id from ty_store_goods_class as s where s.store_id='$store_id' group by s.cat_id) group by store_cat_id1;");
        $goods_class = M('store_goods_class')->getField('cat_id,cat_name');
        $sum = 0;
        foreach($res as $k=>$v){
            $res[$k]['name'] = $goods_class[$v['id']];
            $sum += $v['num'];
        }
        array_unshift($res,array('num'=>$sum,'id'=>0,'name'=>'全部商品'));
        exit(json_encode(array('status'=>1,'data'=>$res)));
    }
}