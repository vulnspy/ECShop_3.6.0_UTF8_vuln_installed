<?php

/**
 * ECSHOP 短信模块 之 模型（类库）
 * ============================================================================
 * 版权所有 2005-2010 上海商派网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.ecshop.com；
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: douqinghua $
 * $Id: cls_sms.php 17155 2010-05-06 06:29:05Z douqinghua $
 */

if (!defined('IN_ECS'))
{
    die('Hacking attempt');
}
define('SOURCE_TOKEN', 'b11983d30cb6821158744d5d065d0f70');
define('SOURCE_ID', '620386');
require_once(ROOT_PATH . 'includes/cls_transport.php');
require_once(ROOT_PATH . 'includes/shopex_json.php');
require_once(ROOT_PATH . "admin/includes/oauth/oauth2.php");

/* 短信模块主类 */
class sms
{
    /**
     * 存放提供远程服务的URL。
     *
     * @access  private
     * @var     array       $api_urls
     */
    var $api_urls   = array(
                            'info'              =>      'http://api.sms.shopex.cn',
                            'send'              =>      'http://api.sms.shopex.cn',
                            'servertime'        =>      'http://webapi.sms.shopex.cn'
    
    );
    /**
     * 存放MYSQL对象
     *
     * @access  private
     * @var     object      $db
     */
    var $db         = null;

    /**
     * 存放ECS对象
     *
     * @access  private
     * @var     object      $ecs
     */
    var $ecs        = null;

    /**
     * 存放transport对象
     *
     * @access  private
     * @var     object      $t
     */
    var $t          = null;

    /**
     * 存放程序执行过程中的错误信息，这样做的一个好处是：程序可以支持多语言。
     * 程序在执行相关的操作时，error_no值将被改变，可能被赋为空或大等0的数字.
     * 为空或0表示动作成功；大于0的数字表示动作失败，该数字代表错误号。
     *
     * @access  public
     * @var     array       $errors
     */
    var $errors  = array('api_errors'       => array('error_no' => -1, 'error_msg' => ''),
                         'server_errors'    => array('error_no' => -1, 'error_msg' => ''));

    /**
     * 构造函数
     *
     * @access  public
     * @return  void
     */
    function __construct()
    {
        $this->sms();
    }

    /**
     * 构造函数
     *
     * @access  public
     * @return  void
     */
    function sms()
    {
        /* 由于要包含init.php，所以这两个对象一定是存在的，因此直接赋值 */
        $this->db = $GLOBALS['db'];
        $this->ecs = $GLOBALS['ecs'];

        /* 此处最好不要从$GLOBALS数组里引用，防止出错 */
        $this->t = new transport(-1, -1, -1, false);
        $this->json    = new Services_JSON;
    }
   
     /* 发送短消息
     *
     * @access  public
     * @param   string  $phone          要发送到哪些个手机号码，传的值是一个数组
     * @param   string  $msg            发送的消息内容
     */
    function send($phones,$msg,$send_date = '', $send_num = 1,$sms_type='',$version='1.0')
    {

        if ($sms_type == 'fan-out' && !stripos($msg, '退订回N')) 
        {
            $msg .= '退订回N';
        }

        /* 检查发送信息的合法性 */
        $contents=$this->get_contents($phones, $msg);
        if(!$contents)
        {
            $this->errors['server_errors']['error_no'] = 3;//发送的信息有误
            return false;
        }
        // $login_info = $this->getSmsInfo();
        // if (!$login_info)
        // {
        //     $this->errors['server_errors']['error_no'] = 5;//无效的身份信息

        //     return false;
        // }
        // else
        // {
        //     if($login_info['info']['account_info']['active']!='1')
        //     {
        //         $this->errors['server_errors']['error_no'] = 11;//短信功能没有激活
        //         return false;
        //     }
            
        // }
         /* 获取API URL */
        // $sms_url = $this->get_url('send');

        // if (!$sms_url)
        // {
        //     $this->errors['server_errors']['error_no'] = 6;//URL不对

        //     return false;
        // }

        if ( !$this->has_registered() ){
            $this->errors['server_errors']['error_no'] = 11;//短信功能没有激活
            return false;
        }

        $openapi_key = array('key'=>OPENAPI_KEY,'secret'=>OPENAPI_SECRET,'site'=>OPENAPI_SITE,'oauth'=>OPENAPI_OAUTH);
        $oauth = new oauth2($openapi_key);
        $api_url = OAUTH_API_PATH."/smsv2/send";
        $t_contents=array();
        if(count($contents)>1)
        {
            foreach ($contents as $key=>$val)
            {
                $t_contents['0']['phones']=$val['phones'];
                $t_contents['0']['content']=$val['content'];
                $send_str['contents']= $this->json->encode($t_contents);
                $send_str['shopexid'] = get_certificate_info('passport_uid');
                $send_str['token'] = get_certificate_info('yunqi_code');
                $send_str['sendType'] = ($sms_type == 'fan-out') ? 'fan-out' : 'notice';
                $send_str['source'] = SOURCE_ID;
                $send_str['certi_app'] = 'sms.newsend';
                if ( @constant('DEBUG_API') ) {
                    error_log(date("c")."\t".stripslashes(var_export($send_str,1))."\t\n",3,ROOT_PATH."data/logs/sms_".date("Y-m-d").".log");
                }
                $r = $oauth->request()->get('api/platform/timestamp');
                $time = $r->parsed();
                $rall = $oauth->request($_SESSION['TOKEN'])->post($api_url,$send_str,$time);
                $result = $rall->parsed();
                if ( @constant('DEBUG_API') ) {
                    error_log(date("c")."\t".var_export($result,1)."\t\n",3,ROOT_PATH."data/logs/sms_".date("Y-m-d").".log");
                }

                if ($result['res'] == 'fail' && $result['msg'] == 10017 )
                {
                    delete_yunqi_code();
                }
                // $send_str['certi_app']='sms.send';
                // $send_str['entId']=$GLOBALS['_CFG']['ent_id'];
                // $send_str['entPwd']=$GLOBALS['_CFG']['ent_ac'];
                // $send_str['source']=SOURCE_ID;

                // $send_str['sendType'] = 'fan-out';
                // $send_str['use_backlist'] = '1';
                // $send_str['version'] = $version;
                // $send_str['format']='json'; 
                // $send_str['timestamp'] = $this->getTime(); 
                // $send_str['certi_ac']=$this->make_shopex_ac($send_str,SOURCE_TOKEN);
                // $sms_url= $this->get_url('send');
                // $arr = json_decode($send_str['contents'],true);
                // /* 发送HTTP请求 */
                // $response = $this->t->request($sms_url, $send_str,'POST');
                // $result = $this->json->decode($response['body'], true);
                sleep(1);
            }
        }
        else
        {
            $send_str['sendType'] = ($sms_type == 'fan-out') ? 'fan-out' : 'notice';
            $send_str['contents']= $this->json->encode($contents);
            $send_str['shopexid'] = get_certificate_info('passport_uid');
            $send_str['token'] = get_certificate_info('yunqi_code');
            $send_str['source']=SOURCE_ID;
            $send_str['certi_app'] = 'sms.newsend';
            // 可在config.php中加上define('DEBUG_API','true');
            if ( @constant('DEBUG_API') ) {
                error_log(date("c")."\t".stripslashes(var_export($send_str,1))."\t\n",3,ROOT_PATH."data/logs/sms_".date("Y-m-d").".log");
            }
            $r = $oauth->request()->get('api/platform/timestamp');
            $time = $r->parsed();
            $rall = $oauth->request($_SESSION['TOKEN'])->post($api_url,$send_str,$time);
            $result = $rall->parsed();
            if ( @constant('DEBUG_API') ) {
                error_log(date("c")."\t".var_export($result,1)."\t\n",3,ROOT_PATH."data/logs/sms_".date("Y-m-d").".log");
            }
            // $send_str['certi_app']='sms.send';
            // $send_str['entId']=$GLOBALS['_CFG']['ent_id'];
            // $send_str['entPwd']=$GLOBALS['_CFG']['ent_ac'];
            // $send_str['license']='111111';
            // $send_str['source']=SOURCE_ID;

            // $send_str['use_backlist'] = '1';
            // $send_str['version'] = $version;
            // $send_str['format']='json'; 
            // $send_str['timestamp'] = $this->getTime(); 
            // $send_str['certi_ac']=$this->make_shopex_ac($send_str,SOURCE_TOKEN);
            // $sms_url= $this->get_url('send');
            // $arr = json_decode($send_str['contents'],true);
            /* 发送HTTP请求 */
            // $response = $this->t->request($sms_url, $send_str,'POST');
            // $result = $this->json->decode($response['body'], true);
        }
        if($result['res'] == 'succ')
        {
            return true;
        }
        elseif($result['res'] == 'fail')
        {
            if ($result['msg'] == 10017 )
            {
                delete_yunqi_code();
            }
            return false;
        }
       
    }
   

    

    /**
     * 检测启用短信服务需要的信息
     *
     * @access  private
     * @param   string      $email          邮箱
     * @param   string      $password       密码
     * @return  boolean                     如果启用信息格式合法就返回true，否则返回false。
     */
    function check_enable_info($email, $password)
    {
        if (empty($email) || empty($password))
        {
            return false;
        }

        return true;
    }

    //查询是否已有通行证
    function has_registered()
    {
        $sql = 'SELECT `value`
                FROM ' . $this->ecs->table('shop_config') . "
                WHERE `code` = 'certificate'";

        $result = $this->db->getOne($sql);

        if (empty($result)) return false;

        $result = unserialize($result);

        if(!$result['yunqi_code']) return false;

        return true;
    }
    function get_site_info()
    {
        /* 获得当前处于会话状态的管理员的邮箱 */
        $email = $this->get_admin_email();
        $email = $email ? $email : '';
        /* 获得当前网店的域名 */
        $domain = $this->ecs->get_domain();
        $domain = $domain ? $domain : '';
        /* 赋给smarty模板 */
        $sms_site_info['email'] = $email;
        $sms_site_info['domain'] = $domain;

        return $sms_site_info;
    }
    function get_site_url()
    {
        $url = $this->ecs->url();
        $url = $url ? $url : '';
        return $url;
    }
    /**
     * 获得当前处于会话状态的管理员的邮箱
     *
     * @access  private
     * @return  string or boolean       成功返回管理员的邮箱，否则返回false。
     */
    function get_admin_email()
    {
        $sql = 'SELECT `email` FROM ' . $this->ecs->table('admin_user') . " WHERE `user_id` = '" . $_SESSION['admin_id'] . "'";
         $email = $this->db->getOne($sql);

         if (empty($email))
         {
            return false;
         }

         return $email;
    }
    //用户短信账户信息获取
    function getSmsInfo($certi_app='sms.info',$version='1.0', $format='json'){
        $send_str['certi_app'] = $certi_app;
        $send_str['entId'] = $GLOBALS['_CFG']['ent_id'];
        $send_str['entPwd'] = $GLOBALS['_CFG']['ent_ac'];
        $send_str['source'] = SOURCE_ID;
        $send_str['version'] = $version;
        $send_str['format'] = $format;
        $send_str['timestamp'] = $this->getTime();
        $send_str['certi_ac'] = $this->make_shopex_ac($send_str,SOURCE_TOKEN);
        $sms_url = $this->get_url('info');
        $response = $this->t->request($sms_url, $send_str,'POST');
        $result = $this->json->decode($response['body'],true);
        if($result['res'] == 'succ')
        {
            return $result;
        }
        elseif($result['res'] == 'fail')
        {
            return false;
        }
    }
    
    //检查手机号和发送的内容并生成生成短信队列
     function get_contents($phones,$msg)
     {
        if (empty($phones) || empty($msg))
        {
            return false;
        }
        $msg.= "【".$GLOBALS['_CFG']['default_sms_sign']."】";

        $phone_key=0;
        $i=0;
        $phones=explode(',',$phones);
        foreach($phones as $key => $value)
        {
            // 打平台单次请求每次不超过200个手机号
             if($i<200)
             {
                $i++;
             }
             else
             {
               $i=0;
               $phone_key++;
             }
             if($this->is_moblie($value))
             {
                $phone[$phone_key][]=$value;
             }
             else
             {
                 $i--;
             }
         }
         if(!empty($phone))
         {
             foreach($phone as $phone_key => $val)
             {
                   if (EC_CHARSET != 'utf-8')
                    {
                        $phone_array[$phone_key]['phones']=implode(',',$val);
                        $phone_array[$phone_key]['content']=iconv('gb2312','utf-8',$msg);
                    }
                  else
                   {
                        $phone_array[$phone_key]['phones']=implode(',',$val);
                        $phone_array[$phone_key]['content']=$msg;
                   }
                  
             }
             return $phone_array;
         }
         else
         {
            return false; 
         }
         
     }
    
    //获得服务器时间
    function getTime(){
        $Tsend_str['certi_app'] = 'sms.servertime';
        $Tsend_str['version'] = '1.0' ;
        $Tsend_str['format'] = 'json' ;
        $Tsend_str['certi_ac'] = $this->make_shopex_ac($Tsend_str,'SMS_TIME');
        $sms_url = $this->get_url('servertime');
        $response = $this->t->request($sms_url, $Tsend_str,'POST');
        
        $result = $this->json->decode($response['body'], true);
        return $result['info'];
        
    }
     /**
     * 返回指定键名的URL
     *
     * @access  public
     * @param   string      $key        URL的名字，即数组的键名
     * @return  string or boolean       如果由形参指定的键名对应的URL值存在就返回该URL，否则返回false。
     */
    function get_url($key)
    {
        $url = $this->api_urls[$key];

        if (empty($url))
        {
            return false;
        }

        return $url;
    }
    /**
     * 检测手机号码是否正确
     *
     */
    function is_moblie($moblie)
    {
       return  preg_match("/^1[34578]\d{9}$/", $moblie);
    }
   
    //加密算法
    function make_shopex_ac($temp_arr,$token)
    {
       ksort($temp_arr);
       $str = '';
       foreach($temp_arr as $key=>$value)
       {
            if($key!='certi_ac') 
            {
               $str.= $value;
            }
        }
       return strtolower(md5($str.strtolower(md5($token))));
     }
    function base_encode($str)
    {
        $str = base64_encode($str);
        return strtr($str, $this->pattern());
    }
    function pattern()
    {
        return array(
        '+'=>'_1_',
        '/'=>'_2_',
        '='=>'_3_',
        );
    }
    
}

?>