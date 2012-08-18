<?php

/**
 * Model to handle the Factory Storage operations on the given table
 */
class factory_model extends CI_Model {

    /**
     * Variable holds the table name which the factory isset to handle
     * @access private
     * @var string 
     */
    var $_table = NULL;

    /**
     * Variable holds the table coulmns info
     * @access private
     * @var array 
     */
    var $_table_info = NULL;

    /**
     * variable holds the last inserted id
     * @var string last inserted id 
     */
    var $_last_id = NULL;

    /**
     * variable holds the last query string
     * @var string last query 
     */
    var $_last_query = NULL;

    /**
     * variable holds number of the affected rows by the last operation
     * @var type 
     */
    var $_rows_affected = 0;

    /**
     * Variable holds number of founded rows neglecting limits
     * @var int 
     */
    var $_found_rows = 0;

    /**
     * variable holds the error message object
     * @var array contains errors 
     */
    var $_error_message = array();

    /**
     * variable holds the current table size
     * @var int Table Size 
     */
    var $_table_size = NULL;

    /**
     * variable holds the output type table size
     * @var string type
     */
    var $_output = "result_array";

    /**
     * holds max packet to insert into db
     * @var int byte represented 
     */
    var $_max_allowed_packet;

    /**
     * holds max packet to insert into db
     * @var int byte represented 
     */
    var $_max_elements_per_batch = 5;

    /**
     * Singleton Object
     * @var factory_model
     */
    private static $instance;

    /**
     * Factory constructor
     * @access public
     */
    public function __construct() {
        if (!self::$instance) {
            parent::__construct();
            self::$instance = $this;
            $this->load->database(); //Loading database
            $this->setOutput("result_array");
            $this->_max_allowed_packet = (int) $this->setOutput('row')->q("SELECT @@global.max_allowed_packet as MaxPacket;")->MaxPacket;
            //max allowed batch size to handle the max allowed memory size
            //$this->_max_allowed_packet = min($this->_max_allowed_packet, ini_get("memory_limit"));
            $this->setDefault();
            log_message("DEBUG", __CLASS__ . ' initialized');
        } else {
            $this->setDefault();
        }
    }

    /**
     * Function to check the factory health
     * @todo check for members and operations 
     * @access public
     * @return \factory_model $this
     */
    public function checkFactory() {
        try {
            if (!isset($this->_table) OR empty($this->_table)) {
                $error = "Table Name supplied does not exist .... factory cannot proceed";
                $this->perpareExceptionMessage($error, FALSE);
                throw new Exception($error);
            }
        } catch (Exception $exc) {
            $this->_exitwith($exc->getMessage(), true);
        }
    }

    /**
     * set the output type and return the current object , If not found in the supported formats it sets an error
     * @param string $type
     * @return \factory_model $this
     */
    public function setOutput($type = 'result_array') {
        try {
            if (!method_exists($this->db->load_rdriver(), $type)) {

                $error = " \"$type\" is UnKnown results format .... factory cannot proceed";
                $this->perpareExceptionMessage($error, FALSE);
                throw new Exception($error);
            } else {
                $this->_output = $type;
            }
        } catch (Exception $exc) {
            $this->_exitwith($exc->getMessage(), true);
        }
        return $this;
    }

    /**
     * function to set the error message of the model can take string or array and it's wrapper for array_push
     * @param Mixed $error_string 
     * @access public
     */
    public function setErrorMessage($error_occurred) {
        //by array_push it pushes the input array ,string into the error_message array
        array_push($this->_error_message, $error_occurred);
    }

    /**
     * function to return the error message with the given format
     * @param type $error_occurred
     * @param type $return 
     * @access public
     */
    public function getErrorMessage($format = "array") {
        $res = NULL;
        //switch on the format comming
        switch ($format) {
            case "array":
                $res = $this->_error_message;
                break;
            case "json":
                $res = json_encode($this->_error_message);
                break;
            case "text":
                $res = arrayToSTR($this->_error_message, "</br>");
                break;
            default:
                break;
        }
        return $res;
    }

    /**
     * function must be called to set the factory to work on the given table
     * @param string $table Table Name
     * @access public
     * @return \factory_model $this
     */
    public function setTable($table) {
        try {
            if (empty($table) || !$this->db->table_exists($table)) {
                $error = "Table does not exist ... factory cannot proceed";
                $this->perpareExceptionMessage($error, FALSE);
                throw new Exception($error);
            } else {
                $this->_table = $table;
                $this->setTableSize();
                $this->setTableInfo();
            }
        } catch (Exception $exc) {
            $this->_exitwith($exc->getMessage(), true);
        }
        return $this;
    }

    /**
     * Function to reset factory settings
     * @access public
     */
    public function setDefault() {
        $this->_error_message = array();
        $this->_last_id = NULL;
        $this->_rows_affected = 0;
        $this->_table = NULL;
        $this->_last_query = NULL;
        $this->_table_info = NULL;
        $this->_table_size = NULL;
        $this->_output = 'result_array';
    }

    /**
     * function to switch the working table of the factory
     * @param string $table the new table name
     * @access public
     */
    public function switchTable($table) {
        try {
            if (empty($table) || !$this->db->table_exists($table)) {
                $error = "Table does not exist ... factory cannot proceed";
                $this->perpareExceptionMessage($error, FALSE);
                throw new Exception($error);
            } else {
                $this->_table = $table;
                $this->setTableSize();
                $this->setTableInfo();
            }
        } catch (Exception $exc) {
            $this->_exitwith($exc->getMessage(), true);
        }
        return $this;
    }

    /**
     * function to update the factory table with the given data where the given criteria
     * @param array $set new data to set
     * @param array $where criteria of updating
     */
    public function update($set, $where = array()) {
        $this->checkFactory();
        $res = NULL;
        !empty($set) ? $this->db->set($set) : NULL;
        !empty($where) ? $this->db->where($where) : NULL;
        try {
            $result = $this->db->update($this->_table);
            if ($this->db->_error_message()) {
                $message = "Cannot update Data";
                $this->perpareExceptionMessage($message, TRUE, $this->db->_error_message());
                throw new Exception($message);
            }
        } catch (Exception $e) {
            $this->_exitwith($e->getMessage(), true);
        }
        return $this->_exitwith($result);
    }

    /**
     * Function to delete data 
     * @param array $where where conditions
     * @return mixed result
     * @throws Exception 
     */
    public function delete($where = array()) {
        $result = NULL;
        $this->checkFactory();
        try {
            if (!$this->db->delete($this->_table, $where)) {
                throw new Exception("cannot delete data");
            }
        } catch (Exception $e) {
            $this->_exitwith($e->getMessage(), true);
        }
        return $result;
    }

    /**
     * Wrapper for query function 
     * @param string $query query to exec
     * @param array $vars optional parameters for pre-stats
     * @return array $result
     * @throws Exception 
     * @see db->query()
     */
    public function q($query, $vars = array()) {
        $result = NULL;
        //default operation is write
        $operation = 'write';
        if (!empty($query))
            try {
                //Checking for read/write query in case of read query return -> (object) else boolean true success false falier
                if (stristr($query, "SELECT") && !(stristr($query, "INSERT") || stristr($query, "UPDATE") || stristr($query, "DELETE"))) {
                    $operation = "read";
                }
                if ($operation == 'read') {
                    $result = $this->db->query($query, $vars)->{$this->_output}();
                } else {
                    $result = $this->db->query($query, $vars);
                }
                if ($this->db->_error_message()) {
                    throw new Exception("cannot execute query");
                }
            } catch (Exception $e) {
                $this->_exitwith($e->getMessage() . $this->_last_query, true);
            }
        return $this->_exitwith($result);
    }

    /**
     * Function to get foreign relation between current and foreign table
     * @param string $select like normal columns to select * for all
     * @param string $l_col local column
     * @param string $foreign foreign table
     * @param s     $this->intring $f_col  foreign column
     * @param string $local optional local table
     */
    function getRelation($select, $l_col, $foreign, $f_col, $local = "") {
        $this->db->select($select);
        $this->db->from(empty($local) ? $this->_table : $local);
        $this->db->join($foreign, "$l_col = $f_col");
        $result = $this->db->get();
        return $result;
    }

    /**
     * Wrapper Function for delete(activerecord) it removes data from related tables based on the given array (tablename=>where_confition)
     * @param array assoc $where
     * @return boolean delete results
     * @example 
     * <b>Call with</b>
     * <pre>
     *      $tables = array(
     *          "articles"  => array("FeedId" => 2),
     *          "feeds"     => array("Id" => 2)
     *          );
     * </pre>
     * @throws Exception Error occured during deleting
     */
    public function deleteTablesData($tables_conditions) {
        $result = NULL;
        $this->checkFactory();
        try {
            foreach ($tables_conditions as $table => $where) {
                $result = $this->db->delete($table, $where);
                $this->_rows_affected += $this->db->affected_rows();
                if ($this->db->_error_message()) {
                    $message = "Cannot Delete Data";
                    $this->perpareExceptionMessage($message);
                    throw new Exception($message);
                }
            }
        } catch (Exception $e) {
            $this->_exitwith($e->getMessage(), true);
        }
        return $result;
    }

    /**
     * Function to insert or update on duplicate values
     * @param mixed $tableData
     * @param string $method [single|batch]
     * @return boolean 
     */
    public function insertOnDuplicateKeyUpdate($tableData, $method = "single") {
        $this->checkFactory();
        $rows_affected = 0;
        if (!is_array($tableData)) {
            $this->_exitwith("Data must be provided in the form of array", true);
        }
        if ($method == "batch") {
            if (arraySize($tableData) > $this->_max_allowed_packet) {
                $chunks = array_chunk($tableData, $this->_max_elements_per_batch);
            }
            if (empty($chunks)) {//chunks is empty so it's small ammount
                foreach ($tableData as $insert) {//for every record
                    unset($columnNames, $updateValues, $insertValues);
                    foreach ($insert as $column => $value) {
                        $value = mysql_escape_string($value);
                        $columnNames[] = $column;
                        $insertValues[] = "'" . $value . "'";
                        $updateValues[] = $column . " = '" . $value . "'";
                    }
                    $res = $this->db->query("insert into $this->_table(" . implode(', ', $columnNames) . ") values(" . implode(', ', $insertValues) . ") on duplicate key update " . implode(', ', $updateValues));
                    try {
                        if ($this->db->_error_message()) {
                            $message = "Error ocurred while inserting data";
                            $this->perpareExceptionMessage($message);
                            throw new Exception($message);
                        }
                    } catch (Exception $exc) {
                        $this->_exitwith($exc->getMessage(), TRUE);
                    }
                    $rows_affected += $res ? $this->db->affected_rows() : 0;
                }
            } else {//
                foreach ($chunks as $chunk) {
                    foreach ($chunk as $insert) {//for every article
                        unset($columnNames, $updateValues, $insertValues);
                        foreach ($insert as $column => $value) {
                            $value = mysql_escape_string($value);
                            $columnNames[] = $column;
                            $insertValues[] = "'" . $value . "'";
                            $updateValues[] = $column . " = '" . $value . "'";
                        }
                        $res = $this->db->query("insert into $this->_table(" . implode(', ', $columnNames) . ") values(" . implode(', ', $insertValues) . ") on duplicate key update " . implode(', ', $updateValues));
                        try {
                            if ($this->db->_error_message()) {
                                $message = "Error ocurred while inserting data";
                                $this->perpareExceptionMessage($message);
                                throw new Exception($message);
                            }
                        } catch (Exception $exc) {
                            $this->_exitwith($exc->getMessage(), TRUE);
                        }
                        $rows_affected += $res ? $this->db->affected_rows() : 0;
                        $this->db->save_queries = FALSE;
                        unset($insert);
                    }
                    unset($chunk);
                }
                unset($chunks);
            }
        } else {
            foreach ($tableData as $column => $value) {
                $columnNames[] = $column;
                $insertValues[] = "'" . $value . "'";
                $updateValues[] = $column . " = '" . $value . "'";
            }
            $res = $this->db->query("insert into $this->_table(" . implode(', ', $columnNames) . ") values(" . implode(', ', $insertValues) . ") on duplicate key update " . implode(', ', $updateValues));
            try {
                if ($this->db->_error_message()) {
                    $message = "Error ocurred while inserting data";
                    $this->perpareExceptionMessage($message);
                    throw new Exception($message);
                }
            } catch (Exception $exc) {
                $this->_exitwith($exc->getMessage(), TRUE);
            }
            $rows_affected += $res ? $this->db->affected_rows() : 0;
        }
        return $this->_exitwith($rows_affected > 0 ? $rows_affected : FALSE);
    }

    /**
     * Wrapper for insert to create batch or make single selection
     * @param mixed $insertData batch or row to insert
     * @param string $mode single(defautl) , batch
     * @return int inserted count 
     * @throws Exception data insertion error
     */
    public function create($insertData, $mode = "single") {
        $this->checkFactory();
        $message = "Occured while inserting Data";
        if (!is_array($insertData)) {
            $this->_exitwith("Data must be provided in the form of array", true);
        }
        try {
            $inserted = 0;
            if ($mode == "batch" && arrayDepth($insertData) > 1) {
                //TODO enhance this part
                $max = $this->_max_allowed_packet;
                if (arraySize($insertData) > $max) {
                    $chunks = array_chunk($insertData, 2);
                    foreach ($chunks as $value) {
                        if ($this->db->insert_batch($this->_table, $value)) {
                            $inserted += $this->db->affected_rows();
                        } else {
                            $this->perpareExceptionMessage($message, TRUE, $this->db->_error_message());
                            throw new Exception($message);
                        }
                    }
                } else {
                    $this->db->insert_batch($this->_table, $insertData);
                    if ($this->db->_error_message()) {
                        $this->perpareExceptionMessage($message, TRUE, $this->db->_error_message());
                        throw new Exception($message);
                    } else {
                        $inserted += $this->db->affected_rows();
                    }
                }
            } else {
                if (arrayDepth($insertData) == 1) {
                    $this->db->insert($this->_table, $insertData);
                    if ($this->db->_error_message()) {
                        $this->perpareExceptionMessage($message, TRUE, $this->db->_error_message());
                        throw new Exception($message);
                    }else
                        $inserted += $this->db->affected_rows();
                } else {
                    $message = " Wrong Data format ";
                    $this->perpareExceptionMessage($message, FALSE);
                    throw new Exception($message);
                }
            }
            return $this->_exitwith($inserted);
        } catch (Exception $e) {
            $this->_exitwith($e->getMessage(), true);
        }
        return $this->_exitwith($res);
    }

    /**
     * Wrapper function for activerecord main functions [select,where,where_in,or_where,join] so takecare of parameters you'r sending
     * @param array $sqlArray 
     * @example
     *      array(
     *              "where_in" => array(array("Id", $data)),
     *              "order_by" => array("Id"),
     *              ...
     *          );
     * @return mixed results @see $this->_output
     * @access public
     * @throws Exception exception in case of wrong criterias
     */
    public function getTableData($sqlArray = array()) {
        //factory checking 
        $this->checkFactory();
        //from clause
        $this->db->from($this->_table);
        $select_is_done = FALSE;
        //prepare the sql query for execution
        try {
            if (!empty($sqlArray)) {//array of sql contains data
                foreach ($sqlArray as $key => $value) {//key is the function name and value is array of the arguments it takes
                    if (!$select_is_done) {
                        if (!array_key_exists('select', $sqlArray)) {
                            $this->db->select("SQL_CALC_FOUND_ROWS *", FALSE);
                            $select_is_done = TRUE;
                        } else {
                            if (strtolower($key) == "select") {
                                array_unshift($value, "SQL_CALC_FOUND_ROWS");
                                $value = preg_replace("/SQL_CALC_FOUND_ROWS\,/", "SQL_CALC_FOUND_ROWS ", implode(',', $value));
                                $this->db->select($value, FALSE);
                                $select_is_done = TRUE;
                            }
                        }
                    }
                    if (method_exists($this->db, $key) && strtolower($key) != "select") {
                        call_user_func_array(array($this->db, $key), $value);
                    }
                }
            }
            $result = $this->db->get();
            if ($this->db->_error_message()) {
                $message = "Error occurred while fetching data : {$this->db->_error_message()}";
                $this->perpareExceptionMessage($message);
                throw new Exception($message);
            } else {
                return $this->_exitwith($result->{$this->_output}());
            }
        } catch (Exception $e) {
            $this->_exitwith($e->getMessage(), true);
        }
    }

    /**
     * Called on instantiation of a model to capture the field names
     * In table_info
     * @access	public
     * @return	array table info
     */
    public function setTableInfo() {
        $this->checkFactory();
        $this->_table_info = $this->db->query('SHOW COLUMNS FROM ' . $this->_table)->result_array();
        return $this;
    }

    /**
     * Called on instantiation of a model to capture the table size
     * In table_size
     * @access	public
     * @return	array table info
     */
    public function setTableSize() {
        $this->checkFactory();
        $this->_table_size = $this->db->count_all($this->_table);
        return $this;
    }

    /**
     * function to exit from the factory with setting the factory info
     * @access private
     * @param mixed $param what is the returned data
     * @param boolean strict the operation to check for general errors
     * @param boolean die if it was true with pre for the output
     * @param int $code HTTP response numbe+r default 200(ok)
     */
    private function _exitwith($param, $die = FALSE, $code = "200") {
        $this->_rows_affected = $this->db->affected_rows();
        $this->_last_query = $this->db->last_query();
        $this->_last_id = $this->db->insert_id();
        $this->_found_rows = $this->db->query("SELECT FOUND_ROWS() as Count;")->row_array();
        $die ? show_error((string) $param, $code) : NULL;
        return $param;
    }

    /* ------------------------------------------------------------------------------------- */
    /*                              Helper Functions                                         */
    /* ------------------------------------------------------------------------------------- */

    /**
     * assuming no element will exceed max size this function chunks the huge data into small patches
     * @param array $param batch 
     * @return mixed 
     */
    function data_chunking($param) {
        $batch = array();
        $out = array();
        $batched = FALSE;
        if (is_array($param)) {
            foreach ($param as $k => $value) {
                if ((arraySize($batch) + arraySize($value)) < $this->_max_allowed_packet) {
                    array_push($batch, $value);
                    unset($param[$k]);
                } else {
                    array_push($out, $value);
                    array_push($batch, $value);
                    $batched = TRUE;
                    $batch = array();
                }
                if (!$batched) {
                    array_push($out, $value);
                    $batch = array();
                    $batched = FALSE;
                }
            }
        }
        //done
        if (is_string($param)) {
            //string size > max
            if (strlen($param) > $this->_max_allowed_packet) {
                $out = str_split($param, $max_size);
            }
        }
        return array_filter($out);
    }

    /**
     * functio  to return the array data size
     * @param array $array
     * @return int byte represented 
     */
    function arraySize($array) {
        $size = 0;
        foreach ($array as $key => $value) {
            if (!is_array($value)) {
                $size += strlen($key . $value);
            } else {
                $size += arraySize($value);
            }
        }
        return $size;
    }

    /**
     * function takes ini directive and return it's size in bytes
     * @param string $ini_dir directive value
     * @return int size in bytes 
     */
    function getVarSize($val) {
        if (empty($val))
            return 0;

        $val = trim($val);

        preg_match('#([0-9]+)[\s]*([a-z]+)#i', $val, $matches);

        $last = '';
        if (isset($matches[2])) {
            $last = $matches[2];
        }

        if (isset($matches[1])) {
            $val = (int) $matches[1];
        }
        //squential conversion g then m then k awesome
        switch (strtolower($last)) {
            case 'g':
            case 'gb':
                $val *= 1024;
            case 'm':
            case 'mb':
                $val *= 1024;
            case 'k':
            case 'kb':
                $val *= 1024;
        }
        return (int) $val;
    }

    /**
     * Prepare exceptions messages according to the current development environment
     * @param string $message
     * @param Bool $extraInfoPrint
     * @param mixed $extraInfoData 
     * @access public
     */
    function perpareExceptionMessage(&$message, $extraInfoPrint = TRUE, $extraInfoData = '') {
        if (ENVIRONMENT == 'development' && $extraInfoPrint) {
            if (!empty($extraInfoData)) {
                $extraInfoData = is_array($extraInfoData) || is_object($extraInfoData) ? arrayToSTR($extraInfoData) : $extraInfoData;
                $message .= "<br/> Error : $extraInfoData<br/>In the following process:<br/><p>" . $this->db->last_query() . "</p>";
            }
            else
                $message .= "<br/> In the following process:<br/><p>" . $this->db->last_query() . "</p>";
        }
        $error = $this->db->_error_message();
        if (isset($error) && !empty($error) && ENVIRONMENT == 'development') {
            $message .= "<br/>MySQL Error :<br/>" . $this->db->_error_message();
        }
    }

    /**
     * Destruction and setting defaults 
     */
    public function __destruct() {
        $this->setDefault();
    }

}

/**
 * End of the Factory model
 * Location : application/model/factory_model.php
 */

