<?php

class TestDaemon extends Daemon
{
  protected $dbh       = null;
  
  protected $dbOptions = array();
  
  protected $counter   = 0;
  
  public function setDbOptions(array $dbOptions)
  {
    $this->dbOptions = $dbOptions;
    return $this;
  }
  
  public function doWork() 
  { 
    if ($this->dbh === null)
    {
      $this->dbh = new PDO(
        $this->dbOptions['dsn'], 
        $this->dbOptions['user'], 
        $this->dbOptions['password']);
    }
    
    $data = range(0, 1000);
    foreach ($data as $item)
    {
      $str = implode('::', array('one', 'two', 'three'));
      $this->dbh->exec("INSERT INTO `test` (col_1, col_2) VALUES('".$item."','".$str."')");
    }
    
    ++$this->counter;
    
    if ($this->counter > 100)
    {
      $this->dbh->exec("TRUNCATE TABLE `test`;");
      $this->counter = 0;
    }
  }
}
