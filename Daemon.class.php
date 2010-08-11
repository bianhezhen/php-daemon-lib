<?php

/**
 * Base class for daemons creation.
 * 
 * 
 * Requirements:
 * 
 * - php5.*,
 * - *nix system,
 * - posix php extension,
 * - pcntl php extension.
 *
 * Options list:
 *
 * daemon      bool      daemonize process or not,
 * delay       int       pause between doWork method calls (in seconds),  
 * root_dir    string    path to daemon root dir,
 * log_file    string    log file name, 'daemon.log' by default,
 * log_level   int       logging level, Daemon::LOG_INFO by default,
 * uid         int       system uid,
 * gid         int       system gid,
 * euid        int       system effective uid,
 * egid        int       system effective gid,
 * time_limit  int       time before daemon stop execution (in seconds), if 0 don't stops,
 * pid_file    string    pid file name, 'daemon.pid' by default.
 * 
 */
abstract class Daemon
{
  /**
   * Debug messages.
   * @var int
   */
  const LOG_DEBUG   = 1;

  /**
   * Info messages , level by default.
   * @var int
   */  
  const LOG_INFO    = 2;
  
  /**
   * Warning messages.
   * @var int
   */
  const LOG_WARNING = 3;
  
  /**
   * Error messages.
   * @var int
   */
  const LOG_ERROR   = 4;
  
  /**
   * Critical messages.
   * @var int
   */
  const LOG_CRIT    = 5;
  
  /**
   * Logging level names.
   * @var array
   */
  protected $logLevels = array(
      self::LOG_DEBUG   => 'debug',
  
      self::LOG_INFO    => 'info',
  
      self::LOG_WARNING => 'warning',
  
      self::LOG_ERROR   => 'error',
  
      self::LOG_CRIT    => 'crit'
    );
    
  
  /**
   * 
   * Daemon options.
   * @var array
   */
  protected $options = array();

  /**
   * Pid file resource.
   * @var resource
   */
  protected $pidFp   = null;

  /**
   * Log file resource.
   * @var resource
   */  
  protected $logFp   = null;
  
  /**
   * 
   * Put your code here.
   */
  abstract public function doWork();
  
  /**
   * 
   * Sets daemon options.
   * @param array $options
   */
  public function configure(array $options)
  {
    $this->options = $options;
    
    return $this;
  }
  
  /**
   * Lunches daemon.
   */
  public function run()
  {
    try
    {
      self::checkSystem();
    }
    catch(Exception $e)
    {
      echo $e->getMessage();
      exit(-1);
    }
    
    if ($this->getPid() !== 0)
    {
      $this->log('Script already running.', self::LOG_CRIT);
      exit(-1);      
    }
    
    if ($this->getOption('daemon', true))
    {
      $this->daemonize();
      $this->log('Daemonize.', self::LOG_DEBUG);
    }
    
    $this->setSignalHandlers();
    $this->log('Signals.', self::LOG_DEBUG);
    
    try
    {
      $this->loop();
    }
    catch(Exception $e)
    {
      $this->log('Crit error, native message: '.$e->getMessage(), self::LOG_CRIT);
    }
  }
  
  /**
   * Checks system requirements before daemon runs.
   * 
   * @throws Exception
   */
  public static function checkSystem() 
  {
    if (PHP_OS === 'WINNT')
    {
      throw new Exception('Windows is not supported.');
    }
    
    if (!extension_loaded('posix'))
    {
      throw new Exception('posix extension must be loaded.');
    }
    
    if (!extension_loaded('pcntl'))
    {
      throw new Exception('pcntl extension must be loaded.');
    }
  }
  
  /**
   * Daemonizes script.
   */
  public function daemonize()
  {
    // create child process
    
    $pid = pcntl_fork();
    if ($pid == -1) 
    {
      exit(-1); 
    } 

    // terminate parent process
    
    if ($pid)
    {
      exit(0);
    }
    
    if (posix_setsid() == -1) 
    {
      exit(-1);
    }
    
    $root = $this->getOption('root_dir');
    if ($root)
    {
      chdir($root);
    }
    
    $gid = $this->getOption('gid');
    if ($gid)
    {
      posix_setgid($gid);
    }

    $egid = $this->getOption('egid');
    if ($egid)
    {
      posix_setegid($egid);
    }
    
    $uid = $this->getOption('uid');
    if ($uid)
    {
      posix_setuid($uid);
    }

    $euid = $this->getOption('euid');
    if ($euid)
    {
      posix_seteuid($euid);
    }
    
    $this->setPid($pid);
    
    return $this;
  }
  
  /**
   * Execution loop.
   */
  public function loop()
  {
    $timeLimit = intval($this->getOption('time_limit'));
    $start = time();
    
    while (true) 
    {
      if ($timeLimit > 0 && (time() - $start) >= $timeLimit)
      {
        break;
      }
      
      $this->doWork();
      
      sleep($this->getOption('delay', 10));
    }
  }

  /**
   * 
   * Returns option value by key or default if not found.
   *  
   * @param string $key
   * @param mixed $default
   * 
   * @return mixed
   */
  public function getOption($key, $default = null)
  {
    return array_key_exists($key, $this->options) ? $this->options[$key] : $default;
  }
  
  /**
   * Sets all signal handlers for daemon process.
   * 
   */
  protected function setSignalHandlers()
  {
    pcntl_signal(SIGTERM, array($this, "termHandler"));
    pcntl_signal(SIGILL,  array($this, "killHandler"));
    
    pcntl_signal(SIGINT, array($this, "intHandler"));
    
    pcntl_signal(SIGTSTP, array($this, "tstpHandler"));
  }
  
  protected function intHandler($signo)
  {
    $this->unsetPid();
    exit(0);
  }
  
  protected function termHandler($signo)
  {
    $this->unsetPid();
    exit(0);
  }
  
  protected function killHandler($signo)
  {
    $this->unsetPid();
    exit(0);
  }

  protected function tstpHandler($signo)
  {
    $this->unsetPid();
    exit(0);
  }

  /**
   * @param string message for logging
   * @param int    logging level
   * 
   * @return bool 
   */
  public function log($message, $level)
  {
    $currentLevel = $this->getOption('log_level', self::LOG_INFO);
    
    if ($level > $currentLevel)
    {
      return false;
    }
    
    if (!is_resource($this->logFp))
    {
      $logFile = $this->getOption('log_file', 'daemon.log');
      
      $this->logFp = fopen($logFile, 'w');
      
      flock($this->logFp, LOCK_EX);
    }
    
    $line = sprintf("[%s]\t[%s]\t%s", date('Y-m-d H:i:s'), $this->logLevels[$level], $message);
    
    return (bool) fwrite($this->logFp, $line);
  }
  
  public function closeLog()
  {
    if (is_resource($this->logFp))
    {
      flock($this->logFp, LOCK_UN);
      fclose($this->logFp);
    }  
  }
  
  /**
   * 
   * Sets daemon process pid to pid file.
   * 
   * @param int $pid
   * 
   * @return bool
   */
  protected function setPid($pid)
  {
    $pidFile = $this->getOption('pid_file', 'daemon.pid');
    
    if (!is_resource($this->pidFp))
    {
      $this->pidFp = fopen($pidFile, 'w');
    }
    
    flock($this->pidFp, LOCK_EX);
    $result = fwrite($this->pidFp, $pid);
    
    return (bool) $result;
  }
  
  /**
   * 
   * Returns deamon process pid.
   * 
   * @return int
   */
  protected function getPid()
  {
    $pidFile = $this->getOption('pid_file', 'daemon.pid');
    if (file_exists($pidFile))
    { 
      return intval(file_get_contents($pidFile));
    }
    
    return 0;
  }
  
  /**
   * Deletes daemon pid file.
   *
   * @return bool
   */
  protected function unsetPid()
  {
    if (is_resource($this->pidFp))
    {
      flock($this->pidFp, LOCK_UN);
    }
    
    $pidFile = $this->getOption('pid_file', 'daemon.pid');
     
    if (file_exists($pidFile))
    {
      return unlink($pidFile);
    }
    
    return false;
  }
}