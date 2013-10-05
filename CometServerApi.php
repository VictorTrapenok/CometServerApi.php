<?php
  

define('ADD_USER_HASH', 1);
     
define('SEND_BY_USER_ID', 3); 
define('SEND_GET_LAST_ONLINE_TIME', 5);
define('SEND_EVENT', 6); 
 

define('NO_ERROR', 0);
define('ERROR_USER_OFLINE', -5);
define('ERROR_USER_OFLINE_AND_OVERFLOW_QUEUE', -10);
define('ERROR_USER_OFLINE_MORE_MAX_OFLINE_TIME', -11); 

define('ERROR_USER_CONECTION', -6);

define('ERROR_UNDEFINED_EVENT', -7);
define('ERROR_UNDEFINED_SERVER_EVENT', -1);
 

/**
 * Обращение с номером больше чем размер индекса подключёных пользователей
 */
define('ERROR_MORE_MAX_USER_ID', -8);

/**
 * Обращение с номером больше чем максимальное количество подключёных пользователей
 */
define('ERROR_MORE_MAX_CONECTIONS', -9);

class CometServerApi
{
   var $version=0.8;
 
   static public $server = "comet-server.ru";
   static public $port = 55552;
   static public $timeOut = 1;
   
   static public $dev_id = 1;
   static public $dev_key = "0000000000000000000000000000000000000000000000000000000000000000";
   
   static private $authorization = false;
   
   
   static public $handle = false;
   
   
   /**
    * Выполняет запросы
    * @todo Можно перевести на систему обмена без закрытия соединения после каждого запроса
    * @param string $msg
    * @return array 
    */
   static private function send($msg)
   {       
       if(!self::$handle)
       {
            if(self::$timeOut)
            {
                self::$handle = @fsockopen("d".self::$dev_id.".app.".self::$server, self::$port,$e1,$e2,self::$timeOut);
            }
            else
            {
                self::$handle = @fsockopen(self::$server, self::$port);
            }
       }
       
       if(self::$handle)
       {
           if(!self::$authorization)
           { 
               $msg = "A:::".self::$dev_id.";".self::$dev_key.";".$msg;
               self::$authorization = true;
           }
           //echo "".$msg;
           fputs(self::$handle, $msg, strlen($msg) );
           
           $tmp = fgets(self::$handle);    
           return json_decode($tmp,true);
       }
       return false; 
   }
   
   static public function add_user_hash($user_id, $hash = false)
   {
      if($hash === false)
      {
          $hash = session_id();
      }
      
      return self::send(ADD_USER_HASH.";".$user_id.";".session_id()."");
   }
    
    /**
     * Отправка сообщения списку пользователей
     * @param array $user_id_array Масив с идентификаторами получателей
     * @param array $msg Структура данных которая будет отправлена, должна содержать поле name с именем адресата (chat, и др.)
     * @return array 
     * 
     * Перед отправкой данные конвертируются в json а затем кодируются в base64
     * Структура данных которая будет отправлена принимающий js ожидает увидеть поле name - имя события
     */
   static public function send_by_user_id($user_id_array,$msg)
   {  
        if(!is_array($user_id_array))
        {
            if( (int)$user_id_array > 0)
            {
                $n = 1;
            }
            else
            { 
                return false;
            }
        }
        else
        {
            $n = count($user_id_array);
            foreach ($user_id_array as &$value)
            {
                $value = (int)$value;
                if($value < 1)
                {
                    return false;
                }
            }
            $user_id_array = implode(";",$user_id_array);
        }
        
        if(!is_string($msg))
        {
            $msg = json_encode($msg);
        }
        
      $msg = base64_encode($msg)."";
      return self::send(SEND_BY_USER_ID.";".$n.";".$user_id_array.";".$msg);
   }
    
   /**
    * Отправка сообщения списку пользователей
    * @param type $user_id_array
    * @param type $event_name
    * @param type $msg
    */
   static public function send_to_user($user_id_array, $event_name, $msg)
   {
       self::send_by_user_id($user_id_array,Array("text"=>$msg,"name"=>$event_name) );
   }
   
     /**
      * Определяет количество секунд прошедшее с момента последнего прибывания пользователя online
      * Пример ответа:
      * {"conection":1,"event":5,"error":0,"answer":"0"}
      * 
      * Если answer = 0 то человек online
      * 
      * @param int $user_id
      * @return array 
      */
   static public function get_last_online_time($user_id_array)
   { 
        if(!is_array($user_id_array))
        {
            $n = 1;
        }
        else
        {
            $n = count($user_id_array);
            $user_id_array = implode(";",$user_id_array);
        } 
      return self::send(SEND_GET_LAST_ONLINE_TIME.";".$n.";".$user_id_array.";");
   }
    
    /**
     * Отправляет произвольное сообщение серверу
     * @param int $event_id
     * @param string $msg
     * @param bool $isCoding если true то сообщение перед отправкой будет закодировано в base64
     * @return array 
     */
   static public function send_event($event,$msg, $isCoding = false)
   {
      if($isCoding == false)
      {
          $msg = base64_encode($msg);
      }
      
      $n = 1;
      if( is_array($event) )
      {
          $answer = Array(); 
          foreach ($event as $key => $value)
          {
              $answer[] = self::send(SEND_EVENT.";".$value.";".$msg);
          }
          
          return $answer;
      }
      
      
      return self::send(SEND_EVENT.";".$event.";".$msg);
   }
       
}
?>
