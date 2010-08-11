<?php

declare(ticks = 1);

require_once 'Daemon.class.php';

class SampleDaemon extends Daemon
{
  public function doWork() 
  { 

  }
}

// Run daemon

$d = new SampleDaemon();

$d->configure(array(
  'delay'    => 20,
  'log_level' => Daemon::LOG_DEBUG,
  'root_dir' => '/home/marvin/daemon')
)->run();
