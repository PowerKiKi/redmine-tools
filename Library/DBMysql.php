<?php
//**********************************************************/
// CLASSE D'INTERFACAGE OBJET POUR UNE BD MYSQL(V4.1 ou sup)
//**********************************************************/
//
//	METHODES(resume et paramètres):
//
//			__construct($host="localhost", $user="root", $pwd="")
//					constructeur de la classe
//			__destruct()
//					destructeur de la classe
//			Connect($bdName="")
//					connexion a un serveur BD mysql 4.1 ou sup
//			Disconnect($mode=self::cstModeError)
//					deconnexion a un serveur BD mysql 4.1 ou sup
//			SelectBd($bdName)
//					Selectione une des bases de donnees du serveur
//			Query($query)
//					Execute une requete dans la base en cours du serveur et retourne le resultat (si il y en a un).
//					Retourne FALSE si la requete echoue.
//			TblExist($tblName)
//					Retounre true/false selon que la table existe/n'existe pas
//			EscapeStr($str)
//					formatte une chaine de caractèe au format Mysql
//
//	UTILISATION:
//
//	- CREE L'OBJET D'INTERFACAGE A UN SERVEUR MYSQL 4.1 OU SUP
//		REMARQUE: SI LES PARAMETRES SONT OMIS "localhost", "root" et "" SERONT MIS PAR DEFAUT
//			$mysql = new InterfaceMysqli("hostname","username","pwd");
//
//	- POUR SE CONNECTER A MYSQL
//			$mysql->Connect();
//		OU POUR SE CONNECTER DIRECTEMENT A UNE DES BD
//			$mysql->Connect("nomDeLaBase");
//
//	- POUR SE DECONNECTER
//		REMARQUE: LA DECONNEXION FINALE (EN FIN DE SCRIPT) EST AUTOMATIQUE
//			$mysql->Disconnect();
//
//	- POUR SELECTIONNER UNE BD DU SERVEUR MYSQL
//			$mysql->SelectBd("nomDeLaBase");
//
//	- POUR FAIRE UNE REQUETE SIMPLE
//			$mysql->Query("CREATE TABLE `test` (`test` VARCHAR( 12 ) NOT NULL)");
//
//	- POUR FAIRE UNE REQUETE RETOURNANT DES RESULTATS
//			$result = $mysql->Query("SELECT * FROM `test`");
//		LA METHODE RETOURNE FALSE SI AUCUNE DONNEES N'EST RETOUNREE
//			if (!$mysql->Query("SELECT * FROM `tblUser` WHERE username='Nicolas'")) {echo ("ce username n'existe pas");}
//
//	- POUR SAVOIR SI UNE TABLE EXISTE
//			If ($mysql->TblExist("nomDeLaTable")) { ... }
//
//	- POUR ENLEVER LES CARACTERES QUE LA BD NE SUPPORTE PAS
//			$valueToInsert = mysql->EscapeStr("c'est une valeur avec des ' que mysql n'aime pas");
//
//
//	REMARQUE:
//		- Le module Mysqli doit être active pour php5
//
// Modifications:
// 		- 27.12.7 par mathieu@ecodev.ch => ajout du support de order by pour la fonction: select
//		- 27.12.7 par mathieu@ecodev.ch => la fonction: insert, retourne maintenant l'id de la nouvelle entrée insérée. ($this->bdlink->insert_id) La valeur retournée = 0 si aucun autoincrément existe.
//*********************************************************/
// DECLARATION DE LA CLASSE
//*********************************************************/

class DBMysql {
	protected $hostname, $username, $pwd, $bdLink, $bdName;
	const cstErrConnect = "Impossible de se connecter a la base de donnees";
	const cstErrSelectBD = "Impossible de selectionner la base de donnees";
	const cstErrReconnect = "Connexion a la BD impossible, une autre conexion est deja en cours";
	const cstErrConnInactive = "Impossible car la connexion a la BD est inactive";
	const cstErrQuery = "La requete a echouee";
	const cstModeNoError = 0;
	const cstModeError = 1;

	function __construct($host, $user, $pwd) {

		$this->hostname = $host;
		$this->username = $user;
		$this->pwd = $pwd;
		unset ($this->bdLink);
	}

	function __destruct() {
		self :: disconnect();
	}


// est ce qu'avoir une connexion persistante avec mysqli_pconnect permettrai de gagner en performance ??
	function connect($bdName = "") {
		$this->bdLink = @ mysqli_connect($this->hostname, $this->username, $this->pwd);
	#	$this->bdLink = @ mysqli_connect($this->hostname, $this->username, $this->pwd, '', 3306, '/Applications/MAMP/tmp/mysql/mysql.sock');
		
		self :: GestionErreur(!$this->bdLink, 'Connect - '.self :: cstErrConnect.' '.$this->hostname);
		if ($bdName != "") {
			self :: SelectBd($bdName);
		}
		//request with UTF-8 character set according to http://se.php.net/manual/en/function.mysqli-query.php
		$this->bdLink->query("SET NAMES 'utf8'");
	}

	function disconnect($mode = self :: cstModeError) {
		if ($mode == self :: cstModeError) {
			self :: GestionErreur(!isset ($this->bdLink), "Disconnect - ".self :: cstErrConnInactive);
		}
		@ mysqli_close($this->bdLink);
		unset ($this->bdLink);
	}

	// start manual transaction (make sure mysqli autocommit is turned off)
	function startTransaction() {
		self :: GestionErreur(!isset ($this->bdLink), "Start transaction - ".self :: cstErrConnInactive);
		mysqli_autocommit($this->bdLink,false);
	}

	function commit() {
		self :: GestionErreur(!isset ($this->bdLink), "Commit transaction - ".self :: cstErrConnInactive);
		mysqli_commit($this->bdLink);
	}

	function rollback() {
		self :: GestionErreur(!isset ($this->bdLink), "Rollback transaction - ".self :: cstErrConnInactive);
		mysqli_rollback($this->bdLink);
	}

	function selectBd($bdName) {
		self :: GestionErreur(!isset ($this->bdLink), "SelectBd - ".self :: cstErrConnInactive);
		$this->bdName = $bdName;
		self :: GestionErreur(!@ mysqli_select_db($this->bdLink, $bdName), "SelectBd - ".self :: cstErrSelectBD.' '.$this->bdName);
	}

	function query($query){
		self :: GestionErreur(!isset ($this->bdLink), "Query - ".self :: cstErrConnInactive);
		self :: GestionErreur(!@ mysqli_real_query($this->bdLink, $query), "Query - ".self :: cstErrQuery.' <pre>'.$query . '</pre>');

		//si c'est une requête qui n'est pas cense ramener qqchose on stop
		if (@ mysqli_field_count($this->bdLink) == 0) {
			return true;
		}
		$result = @ mysqli_store_result($this->bdLink);
		if (@ mysqli_num_rows($result) > 0) {
			return $result;
		} else {
			return false;
		}
	}

	function tblExist($tblName) {
		self :: GestionErreur(!isset ($this->bdLink), "TblExist - ".self :: cstErrConnInactive);
		return self :: Query("SHOW TABLES FROM ".$this->bdName." LIKE '".$tblName."'");
	}

	function escapeStr($str) {
		return mysqli_real_escape_string($this->bdLink, $str);
	}

	// callback internal function to be use with array_walk()
	protected function addQuotes(&$item, $key) {
		if($item == '') {
			$item = '""';
		} else {
			$item = "'".$this->escapeStr($item)."'";
		}
	}

	/***********************************************************
	 * GET AN OBJECT FROM	A RESULTSET
	 ***********************************************************/
	function getObject($result,$objectName) {
		$obj = null;
		if ($result != null) {
			$row = $result->fetch_assoc();
			$constructor = 'new '.$objectName.'(';
			foreach($row as $value){
				$constructor = $constructor.'\''.$value.'\',';
			}
			$constructor = substr($constructor,0,strlen($constructor)-1).')'; //delete the last "," and add ")"
			eval("\$obj = $constructor;"); //evalue the constructor
		}
		return $obj;
	}

	/***********************************************************
	 * GET MANY OBJECT FROM	A RESULTSET
	 ***********************************************************/
	function getObjects($result,$objectName) {
		$arrayObjects = array();
		if ($result != null) {
			while ($row = $result->fetch_assoc()) {
				$constructor = 'new '.$objectName.'(';
				foreach($row as $value){
					$constructor = $constructor.'\''.$value.'\',';
				}
				$constructor = substr($constructor,0,strlen($constructor)-1).')'; //delete the last "," and add ")"
				eval("\$obj = $constructor;"); //evalue the constructor
				$arrayObjects[] = $obj;
			}
		}
		return $arrayObjects;
	}
	/***********************************************************
	 * GET ONE ROW ARRAY
	 ***********************************************************/
	function getRowArrays($result) {
		$arrayFromResultSet = array();
		if ($result != null) {
			while ($row = $result->fetch_row()){
				foreach ($row as $value){
					$arrayFromResultSet[] = stripcslashes($value);
				}
			}
		}
		return $arrayFromResultSet;
	}

	/***********************************************************
	 * GET ONE ASSOCIATIVE ARRAY
	 ***********************************************************/
	function getAssocArray($result) {
		$return = array ();
		if ($result != null)
		$return = $result->fetch_assoc();
		return $return;
	}

	/***********************************************************
	 * GET MANY ASSOCIATIVE ARRAY
	 ***********************************************************/
	function getAssocArrays($result) {
		$contentArray = array ();
		if ($result != null) {
			while ($row = $result->fetch_assoc()) {
				$contentArray[] = $row;
			}
		}
		return $contentArray;
	}

	/***********************************************************
	 * INSERT A RECORD FROM AN ASSOCIATIVE ARRAY
	 ***********************************************************/
	function insert($table,$fields) {
		// protect and quote every data to insert
		array_walk($fields,array($this,'addQuotes'));

		$query = "INSERT INTO `$table` (".implode(',',array_keys($fields)).") VALUES (".implode(',',array_values($fields)).")";
		$result = self::query($query);
		// retourne l'id de la nouvelle entrée ou false si une erreur s'est produite
		if($result){
			return $this->bdLink->insert_id;
		}else{
			return false;
		}
	}

	/***********************************************************
	 * DELETE RECORDS FROM AN ASSOCIATIVE ARRAY
	 ***********************************************************/
	function delete($table,$clauses = array()) {
		// protect and quote every data to insert
		array_walk($clauses,array($this,'addQuotes'));

		$query = "DELETE FROM `$table`";
		if(!empty($clauses)){
			foreach($clauses as $key => $value) {
				$clauses2Sql[] = "`$key`=$value";
			}
			$query .= " WHERE ".implode(' AND ',array_values($clauses2Sql))."";
		}
		return self::query($query);
	}

	/***********************************************************
	 * SELECT RECORDS FROM AN ASSOCIATIVE ARRAY  => modif: 27.12.7: ajout du order by
	 ***********************************************************/
	function select($tables,$clauses = array(),$fields = array('*'),$orderBy='') {
		$tables = explode(',',$tables);
		// protect and quote every data to insert
		array_walk($clauses,array($this,'addQuotes'));

		$query = "SELECT ".implode(',',array_values($fields))." FROM `".implode('`,`',array_values($tables))."`";

		if(!empty($clauses)){
			foreach($clauses as $key => $value) {
				$clauses2Sql[] = "`$key`=$value";
			}
			$query .= " WHERE ".implode(' AND ',array_values($clauses2Sql))."";
		}
		if(!empty($orderBy)){
			$query .= " ORDER BY ".$orderBy;
		}
		return self::query($query);
	}

	/***********************************************************
	 * UPDATE SOME FIELDS/RECORDS FROM A TABLE DESIGNATED BY THE CONDITION
	 ***********************************************************/
	function update($table,$fields,$conditions = array(),$debug=false) {
		if (!is_array($fields) || count($fields)==0) {return false;} // no field to modify

		array_walk($fields,array($this,'addQuotes'));
		array_walk($conditions,array($this,'addQuotes'));

		$query = "UPDATE `$table` SET ";
		$params = array();
		foreach($fields as $key => $value){
			$params[] = "$key=$value";
		}
		$query .= implode(',',$params);
			
		foreach($conditions as $key => $value) {
			$clauses[] = "$key=$value";
		}
		if(!empty($conditions))
		$query .= ' WHERE '. implode(' AND ',$clauses);
		if($debug)
		echo $query;
		return self::query($query);
	}

	protected function GestionErreur($testErreur, $msgErreur) {
		if ($this->bdLink) {
			$phpError = mysqli_error($this->bdLink);
			$phpErrorNum = mysqli_errno($this->bdLink);
		} else {
			$phpError = mysqli_connect_error();
			$phpErrorNum = mysqli_connect_errno();
		}
		if ($phpErrorNum != 0)
		$msgPhpError = 'Error n°'.$phpErrorNum.': '.$phpError;
		else
		$msgPhpError = '';
		if ($testErreur) {
			die($msgErreur.'<br/>'.$msgPhpError);
		}
	}
}
?>
