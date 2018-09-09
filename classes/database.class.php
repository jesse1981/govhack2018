<?php
class database {
  protected $pdo;
  var $type;
  var $allowedOps = array(
      "LIKE",
      "=",
      ">",
      ">=",
      "<",
      "<="
    );

  public function __construct($options=array()){
    if (!$options) $options = array(
      "TYPE"  => DB_TYPE,
      "SERVER"=> DB_SERVER,
      "PORT"  => DB_PORT,
      "NAME"  => DB_NAME,
      "USER"  => DB_USER,
      "PASS"  => DB_PASS,
    );
    return $this->connectPDO($options);
  }

  private function connectPDO($options) {
    try {
      $this->type = $options["TYPE"];
      if ($this->type == "psql") $this->type = "pgsql";
      if ($this->type == "isql") $this->type = "firebird";

      $dsn = $this->type.":host=".$options["SERVER"].";port=".$options["PORT"].";dbname=".$options["NAME"];
      $pdo = new PDO($dsn,$options["USER"],$options["PASS"]);
      $this->pdo = $pdo;
    } catch (Exception $ex) {
      $this->pdo = false;
      echo $ex->getMessage();
    }
    return $this->pdo;
  }
  private function getDbType() {
	  return $this->type;
  }

  private function getTables() {
    if ($this->pdo) {
      $result = array();
      $sql = "";
      switch ($this->getDbType()) {
        case "isql":
          $sql = "SELECT rdb$relation_name as table_name
                  FROM rdb$relations
                  WHERE rdb$view_blr is null
                  AND (rdb$system_flag is null or rdb$system_flag = 0)";
          break;
        default:
          $sql = "SELECT table_name
                  FROM information_schema.tables
                  WHERE table_type = 'BASE TABLE'";
          break;
      }
      $tab = $this->query($sql);
      foreach ($tab as $table)
        $result[] = ($this->getDbType()=="isql") ? strtolower($table["TABLE_NAME"]):$table["table_name"];

      return $result;
    }
    else return false;
  }
  private function getFieldDefinitions($table,$getUsage=true) {
    if ($this->pdo) {
      $sql = "";
      switch ($this->getDbType()) {
        case "mysql":
          $sql = "SELECT DISTINCT
                          column_name,
                          data_type,
                          CASE is_nullable
                            WHEN 'YES' THEN 'NO'
                            ELSE 'YES'
                          END as required,
                          EXTRA as extra,
                          character_maximum_length,
                          ordinal_position
                  FROM information_schema.columns
                  WHERE table_name = :table";
          break;
        case "psql":
            $sql = "SELECT
                            g.column_name,
                            g.data_type,
                            g.character_maximum_length,
                            g.udt_name,
                            CASE
                              WHEN is_nullable = 'YES' THEN 'NO'
                              ELSE 'YES'
                            END as required,
                            0 as extra
                    FROM information_schema.columns as g
                    WHERE table_name = :table";
            break;
        case "isql":
          $sql = 'SELECT  r.RDB$FIELD_NAME AS column_name,
                          r.RDB$DESCRIPTION AS field_description,
                          r.RDB$DEFAULT_VALUE AS field_default_value,
                          CASE r.RDB$NULL_FLAG
                            WHEN NULL THEN '."'".'NO'."'".'
                            ELSE '."'".'YES'."'".'
                          END AS required,
                          f.RDB$FIELD_LENGTH AS character_maximum_length,
                          f.RDB$FIELD_PRECISION AS field_precision,
                          f.RDB$FIELD_SCALE AS field_scale,
                          CASE f.RDB$FIELD_TYPE'."
                            WHEN 261 THEN 'BLOB'
                            WHEN 14 THEN 'CHAR'
                            WHEN 40 THEN 'CSTRING'
                            WHEN 11 THEN 'D_FLOAT'
                            WHEN 27 THEN 'DOUBLE'
                            WHEN 10 THEN 'FLOAT'
                            WHEN 16 THEN 'INT64'
                            WHEN 8 THEN 'INTEGER'
                            WHEN 9 THEN 'QUAD'
                            WHEN 7 THEN 'SMALLINT'
                            WHEN 12 THEN 'DATE'
                            WHEN 13 THEN 'TIME'
                            WHEN 35 THEN 'TIMESTAMP'
                            WHEN 37 THEN 'VARCHAR'
                            ELSE 'UNKNOWN'
                          END AS data_type,".'
                          f.RDB$FIELD_SUB_TYPE AS field_subtype,
                          coll.RDB$COLLATION_NAME AS field_collation,
                          cset.RDB$CHARACTER_SET_NAME AS field_charset,
                          0 as extra
                  FROM RDB$RELATION_FIELDS r
                  LEFT JOIN RDB$FIELDS f ON r.RDB$FIELD_SOURCE = f.RDB$FIELD_NAME
                  LEFT JOIN RDB$COLLATIONS coll ON f.RDB$COLLATION_ID = coll.RDB$COLLATION_ID
                  LEFT JOIN RDB$CHARACTER_SETS cset ON f.RDB$CHARACTER_SET_ID = cset.RDB$CHARACTER_SET_ID
                  WHERE r.RDB$RELATION_NAME='."'".':table'."'".'
                  ORDER BY r.RDB$FIELD_POSITION;';
          break;
        case "mssql":
          $sql = "SELECT  column_name,
                          data_type,
                          CHARacter_maximum_length 'character_maximum_length',
                          CASE is_nullable
                            WHEN 'YES' THEN 'NO'
                            ELSE 'YES'
                          END as required,
                          0 as extra
                  FROM information_schema.columns
                  WHERE table_name = :table";
                  break;
      }
      if ($sql) {
        $tab = $this->query($sql,array("table"=>$table));

        // Get the column usage
        if ($getUsage)
          for ($i=0;$i<count($tab);$i++)
            $tab[$i]["column_usage"] = $this->getColumnUsage($table,$tab[$i]["column_name"]);
        return $tab;
      }
      else return false;
    }
    else return false;
  }
  private function getColumnUsage($table,$field) {
    if ($this->pdo) {
      $sql = "";
      switch ($this->getDbType()) {
        case "mysql":
          $sql = "SELECT	TABLE_NAME as source_table,
                          COLUMN_NAME as source_column,
                          CONSTRAINT_NAME as constraint_name,
                          REFERENCED_TABLE_NAME as referenced_table,
                          REFERENCED_COLUMN_NAME as referenced_column
                  FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                  WHERE (TABLE_NAME = :table AND COLUMN_NAME = :field ) OR
                        (REFERENCED_TABLE_NAME = :table AND REFERENCED_COLUMN_NAME = :field )";
          break;
        case "psql":
          $sql = "SELECT	constraint_name,
                          source_table,
                          source_column,
                          target_table as referenced_table,
                          target_column as referenced_column
                  FROM (
                    SELECT
                            o.conname AS constraint_name,
                            (SELECT nspname FROM pg_namespace WHERE oid=m.relnamespace) AS source_schema,
                            m.relname AS source_table,
                            (SELECT a.attname FROM pg_attribute a WHERE a.attrelid = m.oid AND a.attnum = o.conkey[1] AND a.attisdropped = false) AS source_column,
                            (SELECT nspname FROM pg_namespace WHERE oid=f.relnamespace) AS target_schema,
                            f.relname AS target_table,
                            (SELECT a.attname FROM pg_attribute a WHERE a.attrelid = f.oid AND a.attnum = o.confkey[1] AND a.attisdropped = false) AS target_column
                    FROM pg_constraint o
                    LEFT JOIN pg_class c ON c.oid = o.conrelid
                    LEFT JOIN pg_class f ON f.oid = o.confrelid
                    LEFT JOIN pg_class m ON m.oid = o.conrelid
                    WHERE	o.contype = 'f'
                      AND o.conrelid IN (SELECT oid FROM pg_class c WHERE c.relkind = 'r')
                  ) as m
                  WHERE	(source_table = :table AND source_column = :field) OR
                        (referenced_table = '?' AND referenced_column = '?')";
          break;
        case "isql":
          $sql = "SELECT  detail_relation_constraints.RDB$CONSTRAINT_NAME as constraint_name,
                          detail_relation_constraints.RDB$RELATION_NAME as source_table,
                          detail_index_segments.RDB$FIELD_NAME as source_column,
                          master_relation_constraints.RDB$RELATION_NAME as referenced_table,
                          master_index_segments.RDB$FIELD_NAME as referenced_column

                  FROM
                  rdb$relation_constraints detail_relation_constraints
                  JOIN rdb$index_segments detail_index_segments ON detail_relation_constraints.rdb$index_name = detail_index_segments.rdb$index_name
                  JOIN rdb$ref_constraints ON detail_relation_constraints.rdb$constraint_name = rdb$ref_constraints.rdb$constraint_name -- Master indeksas
                  JOIN rdb$relation_constraints master_relation_constraints ON rdb$ref_constraints.rdb$const_name_uq = master_relation_constraints.rdb$constraint_name
                  JOIN rdb$index_segments master_index_segments ON master_relation_constraints.rdb$index_name = master_index_segments.rdb$index_name

                  WHERE detail_relation_constraints.rdb$constraint_type = 'FOREIGN KEY'
                    AND (
                      (detail_relation_constraints.RDB$RELATION_NAME = :table AND detail_index_segments.RDB$FIELD_NAME = :field) OR
                      (master_relation_constraints.RDB$RELATION_NAME = :table AND master_index_segments.RDB$FIELD_NAME = :field)
                    )";
          break;
        case "mssql":
          $sql = "SELECT
                          KCU1.CONSTRAINT_NAME AS constraint_name,
                          KCU1.TABLE_NAME AS source_table,
                          KCU1.COLUMN_NAME AS source_column,
                          KCU1.ORDINAL_POSITION AS ORDINAL_POSITION,
                          KCU2.CONSTRAINT_NAME AS REFERENCED_CONSTRAINT_NAME,
                          KCU2.TABLE_NAME AS referenced_table,
                          KCU2.COLUMN_NAME AS referenced_column,
                          KCU2.ORDINAL_POSITION AS REFERENCED_ORDINAL_POSITION

                  FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS AS RC

                  INNER JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE AS KCU1
                  ON KCU1.CONSTRAINT_CATALOG = RC.CONSTRAINT_CATALOG
                  AND KCU1.CONSTRAINT_SCHEMA = RC.CONSTRAINT_SCHEMA
                  AND KCU1.CONSTRAINT_NAME = RC.CONSTRAINT_NAME

                  INNER JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE AS KCU2
                  ON KCU2.CONSTRAINT_CATALOG = RC.UNIQUE_CONSTRAINT_CATALOG
                  AND KCU2.CONSTRAINT_SCHEMA = RC.UNIQUE_CONSTRAINT_SCHEMA
                  AND KCU2.CONSTRAINT_NAME = RC.UNIQUE_CONSTRAINT_NAME
                  AND KCU2.ORDINAL_POSITION = KCU1.ORDINAL_POSITION

                  WHERE (KCU1.TABLE_NAME = :table AND KCU1.COLUMN_NAME = :field) OR
                        (KCU2.TABLE_NAME = '?' AND KCU2.COLUMN_NAME = '?')";
          break;
      }
      if ($sql) {
        $tab = $this->query($sql,array("table"=>$table,"field"=>$field));

        return $tab;
      }
      else return false;
    }
    else return false;
  }
  private function columnUsage($table,$dbFields) {
    $sql = "";
    foreach ($dbFields as $d)
      foreach ($d["column_usage"] as $u) {
        $jtable = $u["referenced_table"];
        $jfield = $u["referenced_column"];
        if (($jtable!=$table) && ($jtable) && ($jfield)) $sql .= "LEFT JOIN $jtable ON $jtable.$jfield = $table.".$d["column_name"]." ";
      }
    return $sql;
  }
  private function convertType($type) {
    $result = 0;
    switch (strtoupper(substr($type,0,3))) {
      case "VAR":
        $result = PDO::PARAM_STR;
        break;
      case "INT":
        $result = PDO::PARAM_INT;
        break;
      case "BOO":
        $result = PDO::PARAM_BOOL;
        break;
      case "BLO": // TODO: Verify
        $result = PDO::PARAM_LOB;
        break;
      case "TIM":
      $result = PDO::PARAM_STR;
      break;
    }
    return $result;
  }

  public function cleanInts($res) {
    for ($a=0;$a<count($res);$a++)
      for ($b=0;;$b++)
        if (isset($res[$a][$b])) unset($res[$a][$b]);
        else break;
    return $res;
  }
  public function getLastId() {
    if ($this->pdo) return $this->pdo->lastInsertId();
    else return false;
  }
  public function getTypeId($type) {
    $sql = "SELECT id FROM object_types WHERE name = :name";
    $res = $this->query($sql,array("name"=>$type));
    $id  = (isset($res[0])) ? (int)$res[0]["id"]:0;

    return $id;
  }
  public function query($sql,$params=array(),$types=array(),$stripColIndex=true) {
    if ($this->pdo) {
      try {
          // Prepare statement
          $stmt = $this->pdo->prepare($sql);
          if (!$stmt) {
            throw new Exception('Statement Error: ');
          }
          $res  = $stmt->execute($params);
          if (!$res) {
            throw new Exception('Error: Query failed to execute: ');
          }
          if (substr($sql,0,6)=="SELECT") {
            $tab  = $stmt->fetchAll();
            if ($stripColIndex) {
              $index = 0;
              foreach ($tab as $item) {
                foreach ($item as $k=>$v)
                  if (is_numeric($k))
                    unset($tab[$index][$k]);
                $index++;
              }
            }
            return $tab;
          }
          else if (substr($sql,0,6)=="INSERT") {
            $id = $this->getLastId();
            return $id;
          }
          else {
            $rows = $stmt->rowCount();
            return $rows; // Use for Updates
          }

      }
      catch (Exception $ex) {
        if (php_sapi_name()!="cli") {
          echo $ex->getMessage();
          var_dump($stmt->errorInfo());
          echo "<br/>QUERY: $sql<br/>";
          var_dump($params);
          var_dump($types);
          //log_error(implode(":",$stmt->errorInfo()));
        }
      }
    }
    else return false;
  }
  public function put($table,$data=array(),$id=0,$suppress=false) {
    $fields     = array();
    $values     = array();
    $dtypes     = array();
    $dbTables   = $this->getTables();
    $dbFields   = $this->getFieldDefinitions($table,false);
    $id         = (is_numeric($id)) ? (int)$id:0;
    $data       = ($data) ? $data:$_POST;
    $fieldCount = 0;
    if (!$data) {
      echo "Got no values!";
      return false;
    }
    if (in_array($table,$dbTables)) {
      if ($id)    $sql = "UPDATE $table SET ";
      else        $sql = "INSERT INTO $table ";

      // Ensure all required values are met
      foreach ($dbFields as $a=>$v) {
        $fieldCount++;
        if (isset($dbFields[$a]["ordinal_position"]) && (int)$dbFields[$a]["ordinal_position"]!=$fieldCount) break;
        if  (
              ($dbFields[$a]["required"]=="YES") &&
              ((!isset($data[$dbFields[$a]["column_name"]])) || (empty($data[$dbFields[$a]["column_name"]]))) &&
              (strtolower($dbFields[$a]["extra"]) != "auto_increment" ) &&
              (strtolower($dbFields[$a]["column_name"])!="id")
            )
            if (!$suppress) echo 'Value for '.$dbFields[$a]["column_name"].' is required.'."\r\n";
            else return;
      }

      // recursive query of value arrays
      foreach ($data as $k=>$v) {
        if (is_array($v)) {
          foreach ($v as $val) {
            $data[$k] = $val;
            $this->put($table,$data,$id,$suppress);
            return;
          }
        }
      }

      // Discover values
      foreach ($dbFields as $a) {
        if ((isset($data[$a["column_name"]])) && ($a["column_name"]!="id")) {
          $fields[] = $a["column_name"];
          $values[$a["column_name"]] = $data[$a["column_name"]];
          $dtypes[] = $this->convertType($a["data_type"]);
        }
      }

      // Construct rest of query
      if (($id) && ($dbFields)) {
        for ($index=0;$index<count($fields);$index++) {
          if ($index) $sql .= " , ";
          $sql .= $fields[$index]." = :".$fields[$index];
        }
        $values["id"] = $id;
        $dtypes[] = $this->convertType("INT");
        $sql .= " WHERE id = :id";
      }
      else if ($dbFields) {
        // coming with duplicates?
        $fields = array_unique($fields);
        $sql .= "(".implode(",",$fields).") VALUES (".  ":".implode(",:",$fields).")";
      }

      // Execute
      try {
        $this->query($sql,$values,$dtypes); // values : params
      }
      catch (Exception $e) {
        throw $e->getMessage();
      }
      return ($id) ? $id:$this->pdo->lastInsertId();
    }
    else if (!$suppress) log_error("Table $table does not exist!");
  }
  public function get($table,$id=0,$joins=false) {
    $rows     = array();
    $params   = array();
    $fieldTypes = array();
    $types    = array();
    $body     = (!$id) ? trim(file_get_contents('php://input')):"[{\"field\": \"id\",\"op\": \"=\", \"value\":$id}]";

    $dbTables = $this->getTables();
    if (!in_array($table,$dbTables)) log_error("Table $table does not exist!");
    $dbFields = $this->getFieldDefinitions($table);
    if (!$dbFields) log_error("No fields exist for this table!");

    $sql = "SELECT * FROM $table ";

    // Column usage?
    $sql .= ($joins) ? $this->columnUsage($table,$dbFields):"";

    // Where
    if ($body) {
        $filter = json_decode($body,true);
            $sql       .= " WHERE ";
            $valid      = 0;
            $fieldNames = array();
            foreach ($dbFields as $f) {
              $fieldNames[] = $f["column_name"];
              $fieldTypes[$f["column_name"]] = $this->convertType($f["data_type"]);
            }
            foreach ($filter as $f) {
                if ((!isset($f["field"])) || (!isset($f["op"])) || (!isset($f["value"]))) continue;

                if ((in_array($f["field"],$fieldNames)) &&
                    (in_array($f["op"],$this->allowedOps))) {
                    if ($valid) $sql .= " AND ";

                    $params[$f["field"]] = $f["value"];
                    $types[]  = $fieldTypes[$f["field"]];

                    $sql .= "$table." . $f["field"] . " " . $f["op"] . " :" . $f["field"];
                    $valid++;
                }
            }
    }

    $tab  = $this->query($sql,$params,$types);
    if (isset($tab[0])) {
        foreach ($tab as $item) {
            $values = array();
            foreach ($item as $k=>$v) $values[$k] = $v;
            $rows[] = $values;
        }
    }
    else {
        foreach ($dbFields as $f) $values[$f["column_name"]] = "";
        $rows[] = $values;
    }

    return $rows;
  }
  public function makeForm($table,$res=array(),$edit=false) {
    $tabs = $this->getTables();
    $form = "";
    if (in_array($table,$tabs)) {
      $form = '<form name="'.$table.'" id="'.$table.'" method="POST" enctype="multipart/form-data">';
      $defs = $this->getFieldDefinitions($table);

      foreach ($defs as $d) {
        if ($d["column_name"]=="id") continue;
        $value = (isset($res[0][$d["column_name"]])) ? $res[0][$d["column_name"]]:"";
        $form .= '<label for="'.$d["column_name"].'">'.str_replace("_"," ",ucwords($d["column_name"])).'</label>';

        $classes  = ($d["required"]) ? "required":"";
        $length   = (int)$d["character_maximum_length"];
        $type     = strtolower($d["data_type"]);
        $classes .= " ".$type;
        switch ($type) {
          case "varchar":
            if ($length < 16) $classes .= " micro";
            else if ($length >=16 && $length < 32) $classes .= " small";
            else if ($length >=32 && $length < 128) $classes .= " regular";
            else if ($length >=128 && $length < 256) $classes .= " large";
            else if ($length >=256 && $length < 512) $classes .= " jumbo";
            else {
              $form .= '<textarea name="'.$d["column_name"].'" id="'.$d["column_name"].'" class="'.$classes.'">'.$value.'</textarea>';
              continue;
            }
            $type = (strtolower($d["column_name"])!="password") ? "text":"password";
            $form .= '<input type="'.$type.'" name="'.$d["column_name"].'" id="'.$d["column_name"].'" class="'.$classes.'" value="'.$value.'" />';
            break;
          default:
            $form .= '<input name="'.$d["column_name"].'" id="'.$d["column_name"].'" class="'.$classes.'" value="'.$value.'" />';
            break;
        }
      }
      if (isset($res["id"])) $form .= '<input type="hidden" name="id" value="'.$res["id"].'" />';

      $form .= '<input type="reset" value="Clear" class="btn"/> <input type="submit" value="Submit" class="btn btn-primary" />';
      $form .= "</form>";
    }
    return $form;
  }

  public function createBySource($filename) {
    $io = new io;
    $sheet = $io->xlsxLoad($filename)->getActiveSheet();
    if (!$sheet) return ERR_LOAD_SPREADSHEET_FAILED;

    $table = substr(0,basename($filename),strpos(basename($filename),"."));

    while (true) {

    }

    $max = $sheet->getHighestRow();
    for ($i=6;;$i++) {
      if ($sheet->getCell("A$i")->getValue()=="") break;


      $desc = $sheet->getCell("C$i")->getValue();
    }
  }

  public function getObject($type,$id=0) {
    $sql = "SELECT  a.id,
                    a.name,
                    a.label,
                    g.name as group_name,
                    a.type,
                    a.required,
                    a.source
            FROM object_types t
            INNER JOIN type_atts a ON t.id = a.type_id
            INNER JOIN att_groups g ON a.group_id = g.id
            WHERE t.name = :type_name
            ORDER BY g.id,a.id";
    $res = $this->query($sql,array("type_name"=>$type));
    return $res;
  }
  public function storeObjects() {
    $parent   = 0;
    $primary  = "";
    $fields   = array();
    foreach ($_POST as $k=>$v) {
      $temp = explode("-",$k);
      if (isset($temp[1])) {
        $table = $temp[0];
        if (!$primary) $primary = $table;
        else if ($primary != $table) {
          $this->put($primary,$fields,0,true);
          if (!$parent) $parent = $this->getLastId();
          $primary  = $table;
          $fields   = array();
        }
        $v        = ($temp[1]=="password") ? password_hash($v, PASSWORD_BCRYPT, array( 'cost' => 10 )):$v;
        $v        = (isset($temp[2]) && $temp[2] == "*parent*") ? $parent:$v;
        $temp[1]  = (isset($temp[2]) && $temp[2] == "*parent*") ? str_replace("*parent*","",$temp[1]):$temp[1];
        $fields[$temp[1]] = $v;
      }
      else {
        $sql = "INSERT INTO object_meta (object_id,key,value) VALUES (:object_id,:key,:value)";
        $res = $this->query($sql,array(
          "object_id" => $parent,
          "key"       => $k,
          "value"     => $v
        ));
      }
    }
    if ($primary && $fields) $this->put($primary,$fields,0,true);
  }

  public function findTableByField($fields=array()) {
    $results = array();
    $tables = $this->getTables();
    foreach ($tables as $t) {
      $columns = $this->getFieldDefinitions($t);
      if ($columns) {
        foreach ($columns as $c) {
          if (in_array($c["column_name"],$fields)) $results[] = $t;
          break;
        }
      }
    }
    return $results;
  }
  public function explodeKey($res,$key) {
    $results = array();
    foreach ($res as $item)
      $results[] = $item[$key];
    return $results;
  }
}
?>
