<?php

class EzPDO extends PDO {

	public function __construct($dbconf) {
		parent::__construct('mysql:host='.$dbconf->host.';dbname='.$dbconf->database, $dbconf->user, $dbconf->password);
		$this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);	
		$this->exec('SET CHARACTER SET '.$dbconf->charset);
	}

	public function prepare($sql, $options=NULL) {
		$statement = parent::prepare($sql);
		
		if(preg_match('/^\s*select\s/i', $sql))
			$statement->setFetchMode(PDO::FETCH_OBJ);
		
		return $statement;
	}
	
	public function getRow($sqlQuery, $parms=null) {
		$stm = $this->prepare($sqlQuery);
		
		$actualParms = null;
		
		if (is_array($parms))
			$actualParms = $parms;
		else
			$actualParms = array_slice(func_get_args(), 1);
		
		if ($stm->execute($actualParms))
			return $stm->fetch();
		
		return null;
	}
		
	public function getList($sqlQuery) {
		$stm = $this->prepare($sqlQuery);
		
		if ($stm->execute(array_slice(func_get_args(), 1)))
			return $stm->fetchAll();
		
		return null;
	}
		
	public function execute($sqlQuery) {
		$stm = $this->prepare($sqlQuery);
		if ($stm->execute(array_slice(func_get_args(), 1)))
			return $stm;
		
		return false;
	}
}

