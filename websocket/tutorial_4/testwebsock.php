#!/usr/bin/env php
<?php

require_once('./websockets.php');

class echoServer extends WebSocketServer {
  //protected $maxBufferSize = 1048576; //1MB... overkill for an echo server, but potentially plausible for other applications.
  
  protected function process ($user, $message) {
    $message = json_decode($message);
    if($message->tag == 'set_username') {
      $this->login_as($user, $message->username);
      $this->broadcast_online_user($user);
    } else if($message->tag == 'send_message') { 
	if($message->to == 'all'){
 	     $this->broadcast($user,$message->msg);
	} else {
 	     $this->whisper($user,$message->to,$message->msg);
	}
    }
  }
  
  protected function connect($socket) {
    $user = new $this->userClass(uniqid('u'), $socket);
    $this->users[$user->id] = $user;
    $this->sockets[$user->id] = $socket;
    $this->connecting($user);
  }

  protected function login_as($user,$username){
    $this->users[$user->id]->username = $username;
  }

  protected function whisper($user,$to,$message){
    $this->stdout($user->id."> ".$to." :$message");
    $message = json_encode(array('tag' => 'message', 'username' => $user->username, 'message' => $message, 'whisper' => 1));
    $message = $this->frame($message,$user);
    $result = @socket_write($this->sockets[$to], $message, strlen($message));
  }

  protected function broadcast($user,$message){
    $this->stdout($user->id."> all :$message");
    $message = array('tag' => 'message', 'username' => $user->username, 'message' => $message, 'whisper' => 0);
    $this->write_chat_history($message);
    $message = json_encode($message);
    $message = $this->frame($message,$user);
    foreach($this->users as $u){
      if($u->id != $user->id) $result = @socket_write($u->socket, $message, strlen($message));
    }
  }

  protected function broadcast_online_user($user){
    $this->stdout("Server> Broadcasting online user");
    $users = array();
    foreach($this->users as $k => $u){
      $users[$k] = $u->username;
    }
    $message = json_encode(array('tag' => 'broadcast', 'users' => $users));
    $message = $this->frame($message,$user);
    foreach($this->users as $u){
      $result = @socket_write($u->socket, $message, strlen($message));
    }
  }

  protected function write_chat_history($message){
    $file = dirname(__FILE__).'/history.json';
    if(file_exists($file)){
        $input = file_get_contents($file);
        $temp = json_decode($input);
        if(is_null($temp)) $temp = array();
    } else {
        $temp = array();
    }
        array_push($temp, $message);
        $json = json_encode($temp);
        file_put_contents($file, $json);
  }

  protected function connected ($user) {
    // Do nothing: This is just an echo server, there's no need to track the user.
    // However, if we did care about the users, we would probably have a cookie to
    // parse at this step, would be looking them up in permanent storage, etc.
    $this->broadcast_online_user($user);
  }
  
  protected function closed ($user) {
    // Do nothing: This is where cleanup would go, in case the user had any sort of
    // open files or other objects associated with them.  This runs after the socket 
    // has been closed, so there is no need to clean up the socket itself here.
    $this->broadcast_online_user($user);
  }
}

$echo = new echoServer("0.0.0.0","9000");

try {
  $echo->run();
}
catch (Exception $e) {
  $echo->stdout($e->getMessage());
}
