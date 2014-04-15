<?php

/**
 *  Comet Server PHP Библиотека
 *  Библиотека предоставляет простое API для работы с Comet-Server.ru
 *
 *  Copyright 2013, Trapenok Victor. Licensed under the MIT license: http://www.opensource.org/licenses/mit-license.php
*/

define('NO_ERROR', 0);  
define('ERROR_UNDEFINED_EVENT', -7); 
define('ERROR_CONNECTION', -100); 
define('AUTHORIZATION_ERROR', -12); 
 
class comet_response{
    
    protected $data;
    
    public function __construct($data) {
        $this->data = $data;
    }
     
    public function getError()
    {
        if( isset($this->data['error']))
        {
            return $this->data['error'];
        }

        return false;
    }
    
    /**
     * Содержит текстовое пояснение к ошибке если getError() != 0 
     */
    public function getInfo()
    {
        if( isset($this->data['info']))
        {
            return $this->data['info'];
        }

        return false;
    }
    
    /**
     * Ответ на запрос сервера
     * @return Object
     */
    public function getData()
    {
        if( isset($this->data['data']))
        {
            return $this->data['data'];
        }

        return false;
    }
}


/**
 *  Comet Server PHP Библиотека
 *  Библиотека предоставляет простое API для работы с Comet-Server.ru
 *
 *  $comet = CometServerApi::getInstance();
 *  $comet->authorization(1, "0000000000000000000000000000000000000000000000000000000000000000");
 *  $comet->send_event('my_event', Array("data" => "testing") );
 *  $comet->send_event('my_event', "моё сообщение" );
 *
 *  Copyright 2013, Trapenok Victor. Licensed under the MIT license: http://www.opensource.org/licenses/mit-license.php
*/
class CometServerApi
{
   static $version=1.6;
   static $major_version=1;
   static $minor_version=6;

   protected $server = "comet-server.ru";
   protected $port = 808;
   protected $timeOut = 1;

   protected $authorization = false;
   protected $handle = false;

   protected $dev_id = false;
   protected $dev_key = false;

   protected static $_instance;


   protected static $ADD_USER_HASH = 1;
   protected static $SEND_BY_USER_ID = 3;
   protected static $SEND_GET_LAST_ONLINE_TIME = 5;
   protected static $SEND_EVENT = 6;

   protected static $POP_UNDELIVERED_MESSAGES = 9;
   protected static $CLEAR_UNDELIVERED_MESSAGES = 10;
   protected static $COUNT_UNDELIVERED_MESSAGES = 11;
   protected static $SET_PIPE_SETTINGS = 12;
   
   protected static $GET_STAT = 13;
   protected static $COUNT_USERS_IN_PIPE = 14;
   protected static $CLEAR_PIPE_LOG = 15;
   
   /**
    * Конструетор оставлен публичным на тот случай если вам реально понадобится использовать два соединения с комет сервером единовременно но с разными $dev_id и $dev_key
    * Во всех остальных случаях используйте клас как singleton, тоесть вызывая CometServerApi::getInstance()
    *
    * @param int $dev_id Идентификатор разработчика
    * @param string $dev_key Секретный ключ разработчика
    */
   public function __construct($dev_id = false, $dev_key = false)
   {
       if($dev_id !== false)
       {
           $this->dev_id = $dev_id;
       }
       
       if($dev_key !== false)
       {
           $this->dev_key = $dev_key;
       }
   }

   /**
    * @return CometServerApi
    */
   public static function getInstance()
   {
        if (null === self::$_instance) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

   /**
    * Позволяет указать $dev_id и $dev_key, необходимо вызвать один раз перед использованием.
    *
    * Данные для авторизации будут отправлены вместе с первым запросом  к комет серверу.
    * Эта функция отдельного запроса не зоздаёт
    *
    * @param int $dev_id Идентификатор разработчика
    * @param string $dev_key Секретный ключ разработчика
    * @return CometServerApi
    */
   public function authorization($dev_id, $dev_key)
   {
       $this->dev_id = $dev_id;
       $this->dev_key = $dev_key;
       return $this;
   }

   /**
    * Выполняет запросы
    * @todo Можно перевести на систему обмена без закрытия соединения после каждого запроса
    * @param string $msg
    * @return comet_response
    */
   private function send($msg)
   {
       if($this->dev_id === false || $this->dev_key === false)
       {
           return new comet_response(Array("info" => "Не установлен dev_id или dev_key, перед использованием следует вызвать функцию authorization", "error" => ERROR_UNDEFINED_EVENT, "data" => ""));
       }

       if(!$this->handle)
       {
            if($this->timeOut)
            {
                $this->handle = @fsockopen("d".$this->dev_id.".app.".$this->server, $this->port,$e1,$e2,$this->timeOut);
            }
            else
            {
                $this->handle = @fsockopen($this->server, $this->port);
            }
       }

       if($this->handle)
       {
           if(!$this->authorization)
           {
               $msg = "A:::".$this->dev_id.";".self::$major_version.";".self::$minor_version.";".$this->dev_key.";".$msg;
               $this->authorization = true;
           }
           
           $msg = $msg."\t";

	   //  echo  $msg;
           if( @fputs($this->handle, $msg, strlen($msg) ) === false)
           {
               $this->handle = false;
           }

           $tmp = fgets($this->handle);
           //  echo  "[".$tmp."]\n" ;
           return new comet_response(json_decode($tmp,true));
       }
       
       return new comet_response(Array("info" => "Не удалось создать соединение.", "error" => ERROR_CONNECTION, "data" => ""));
   }

   public function add_user_hash($user_id, $hash = false)
   {
      if($hash === false)
      {
          $hash = session_id();
      }

      return $this->send(self::$ADD_USER_HASH.";".$user_id.";".session_id()."");
   }

   /**
    * Отправка сообщения списку пользователей
    * @param type $user_id_array
    * @param type $event_name
    * @param type $msg
    */
   public function send_to_user($user_id_array, $event_name, $msg)
   {
        $msg = Array("data"=>$msg,"event_name"=>$event_name);
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
   
        return $this->send(self::$SEND_BY_USER_ID.";".$n.";".$user_id_array.";".base64_encode(json_encode($msg)));
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
   public function get_last_online_time($user_id_array)
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
      return $this->send(self::$SEND_GET_LAST_ONLINE_TIME.";".$n.";".$user_id_array.";");
   }

   /**
    * Отправляет произвольное сообщение серверу
    * @param int $event_id
    * @param string $msg
    * @return array
    */
   public function send_to_pipe($pipe, $event_name, $msg)
   {  
      return $this->send(self::$SEND_EVENT.";".$pipe.";".base64_encode(json_encode( Array("data"=>$msg,"event_name"=>$event_name) )));
   }

   /**
    * Устанавливает длину лога сообщений в канале.
    * @param string $pipe
    * @param int $log_len
    * @return array
    */
   public function set_pipe_settings($pipe, $log_len)
   {  
      $type = '0';
      return $this->send(self::$SET_PIPE_SETTINGS.";".$type.';'.((int)$log_len).';'.$pipe);
   }
   
   /**
    * Извлекает одно сообщение из очереди пользователя удалая его из очереди
    * Формат: &event, &user_id, &type
    *
    * type - 1 Выборка с конца очереди (то что попало в очередь раньше других)
    * type - 2 Выборка с начала очереди (то что попало в очередь позже других)
    * 
    */
   protected function pop_undelivered_messages($user_id, $type)
   {
      return $this->send(self::$POP_UNDELIVERED_MESSAGES.";".$user_id.";".$type);
   }
   
   /**
    * Извлекает то сообщение которое попало в очередь раньше других и удаляет его из очереди
    * @return comet_response
    */
   public function pop_first_undelivered_messages($user_id)
   {
      return $this->pop_undelivered_messages($user_id, 1);
   }
   
   /**
    * Извлекает то сообщение которое попало в очередь позже других и удаляет его из очереди
    * @return comet_response
    */
   public function pop_last_undelivered_messages($user_id)
   {
      return $this->pop_undelivered_messages($user_id, 2);
   }
   
   /**
    * Очищает очередь недоставленных сообщений для массива пользователей
    * @return comet_response
    */
   public function clear_undelivered_messages($user_id_array)
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
      return $this->send(self::$CLEAR_UNDELIVERED_MESSAGES.";".$n.";".$user_id_array.";"); 
   }
   
   /**
    * Получает количество недоставленных сообщения для массива пользователей
    * @return comet_response
    */
   public function count_undelivered_messages($user_id_array)
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
      return $this->send(self::$COUNT_UNDELIVERED_MESSAGES.";".$n.";".$user_id_array.";"); 
   }
   
   /**
    * Возвращает статистику сервера на момент вызова
    * @return comet_response
    */
   public function get_stat()
   {
      return $this->send(self::$GET_STAT.";");
   }
    
   public function count_users_in_pipe($pipe)
   {
      return $this->send(self::$COUNT_USERS_IN_PIPE.";".$pipe);
   }
   
   /**
    * Очищает лог в канале
    * @param type $pipe
    * @return type
    */
   public function clear_pipe_log($pipe)
   {
      return $this->send(self::$CLEAR_PIPE_LOG.";".$pipe);
   }
}
