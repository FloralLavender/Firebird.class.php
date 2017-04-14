<?php

class FirebirdDriverException extends Exception {}
class FirebirdException extends FirebirdDriverException {}

class Firebird {
    protected $connection;
    protected $trans;

    // For persistent connections prepend $database with 'p;'
    public function __construct($database=NULL, $username=NULL, $password=NULL,
        $charset=NULL, $buffers=NULL, $dialect=NULL, $role=NULL, $sync=NULL) {
        if (!is_null($database) && stripos($database, 'p;') === 0) {
            $this->connection = @ibase_pconnect(substr($database, 2,
                strlen($database)), $username, $password, $charset, $buffers,
                $dialect, $role, $sync);
        } else {
            $this->connection = @ibase_connect($database, $username, $password,
                $charset, $buffers, $dialect, $role, $sync);
        }
        if ($this->connection === FALSE) {
            throw new FirebirdDriverException(ibase_errmsg(), ibase_errcode());
        }
        $this->trans = NULL;
    }

    function __destruct() {
        if (!is_null($this->trans)) {
            ibase_rollback($this->trans);
        }
        if ($this->connection !== FALSE) {
            ibase_close($this->connection);
        }
    }

    protected function _getConnection() {
        if (!is_null($this->connection)) {
            return $this->connection;
        }
        throw new FirebirdException('Database connection was closed', 0);
    }

    protected function _getResource() {
        if (!is_null($this->trans)) {
            return $this->trans;
        }
        if (!is_null($this->connection)) {
            return $this->connection;
        }
        throw new FirebirdException('Database connection was closed', 0);
    }

    protected function _getTransaction() {
        if (!is_null($this->trans)) {
            return $this->trans;
        }
        throw new FirebirdException('Transaction was closed', 1);
    }

    public static function errCode() {
        return ibase_errcode();
    }

    public static function errMsg() {
        return ibase_errmsg();
    }

    public function affectedRows() {
        return ibase_affected_rows($this->_getResource());
    }

    public function blobCreate() {
        $blob_handle = @ibase_blob_create($this->_getConnection());
        if ($blob_handle === FALSE) {
            throw new FirebirdDriverException(ibase_errmsg(), ibase_errcode());
        }
        return new FirebirdBlob($this->_getConnection(), $blob_handle, TRUE);
    }

    public function blobEcho($blob_id) {
        if (@ibase_blob_echo($this->connection, $blob_id) === FALSE) {
            throw new FirebirdDriverException(ibase_errmsg(), ibase_errcode());
        }
        return TRUE;
    }

    public function blobImport($file_handle) {
        $blob_id = @ibase_blob_import($this->_getConnection(), $file_handle);
        if ($blob_id === FALSE) {
            throw new FirebirdDriverException(ibase_errmsg(), ibase_errcode());
        }
        return $blob_id;
    }

    public function blobInfo($blob_id) {
        $info = @ibase_blob_info($this->connection, $blob_id);
        if ($info === FALSE) {
            throw new FirebirdDriverException(ibase_errmsg(), ibase_errcode());
        }
        return $info;
    }

    public function blobOpen($blob_id) {
        return new FirebirdBlob($this->_getConnection(), $blob_id);
    }

    public function commit() {
        if (@ibase_commit($this->_getResource()) === FALSE) {
            throw new FirebirdDriverException(ibase_errmsg(), ibase_errcode());
        }
        $this->trans = NULL;
        return TRUE;
    }

    public function commitRet() {
        if (@ibase_commit_ret($this->_getResource()) === FALSE) {
            throw new FirebirdDriverException(ibase_errmsg(), ibase_errcode());
        }
        return TRUE;
    }

    public function dropDb() {
        if (@ibase_drop_db($this->_getConnection()) === FALSE) {
            throw new FirebirdDriverException(ibase_errmsg(), ibase_errcode());
        }
        $this->connection = NULL;
        $this->trans = NULL;
        return TRUE;
    }

    public function genId($generator, $increment=1) {
        $new_value = @ibase_gen_id($generator, $increment,
            $this->_getConnection());
        if ($new_value === FALSE) {
            throw new FirebirdDriverException(ibase_errmsg(), ibase_errcode());
        }
        return $new_value;
    }

    public function inTransaction() {
        return !is_null($this->trans);
    }

    public function prepare($query) {
        if (count($this->trans) === 0) {
            $query = @ibase_prepare($this->_getConnection(), $query);
        } else {
            $query = @ibase_prepare($this->_getConnection(),
                $this->_getTransaction(), $query);
        }
        if ($query === FALSE) {
            throw new FirebirdDriverException(ibase_errmsg(), ibase_errcode());
        }
        return new FirebirdStmt($this->_getConnection(), $query);
    }

    public function query($query, $bind_args=NULL) {
        if (is_null($bind_args)) {
            $result = @ibase_query($this->_getResource(), $query);
        } else {
            $result = @ibase_query($this->_getResource(), $query, $bind_args);
        }
        if ($result === FALSE) {
            throw new FirebirdDriverException(ibase_errmsg(), ibase_errcode());
        } else if ($result === TRUE) {
            return TRUE;
        } else if (is_resource($result)) {
            return new FirebirdResult($this->_getConnection(), $result);
        }
        return $result;
    }

    public function rollback() {
        if (@ibase_rollback($this->_getResource()) === FALSE) {
            throw new FirebirdDriverException(ibase_errmsg(), ibase_errcode());
        }
        $this->trans = NULL;
        return TRUE;
    }

    public function rollbackRet() {
        if (@ibase_rollback_ret($this->_getResource()) === FALSE) {
            throw new FirebirdDriverException(ibase_errmsg(), ibase_errcode());
        }
        return TRUE;
    }

    public function setEventHandler(/* $event_handler, $event_name1 to 15 */) {
        $args = func_get_args();
        array_unshift($args, $this->_getConnection());
        $event_handler = @call_user_func_array('ibase_set_event_handler',
            $args);
        if (!is_resource($event_handler)) {
            throw new FirebirdDriverException(ibase_errmsg(), ibase_errcode());
        }
        return new FirebirdEventHandler($event_handler);
    }

    public function trans($trans_args=IBASE_DEFAULT) {
        if (!is_null($this->trans)) {
            $this->rollback();
        }
        $trans = @ibase_trans($this->_getConnection(), $trans_args);
        if ($trans === FALSE) {
            throw new FirebirdDriverException(ibase_errmsg(), ibase_errcode());
        }
        $this->trans = $trans;
        return TRUE;
    }

    public function waitEvent(/* $event_name1 to 15 */) {
        $args = func_get_args();
        array_unshift($args, $this->_getConnection());
        return call_user_func_array('ibase_wait_event', $args);
    }
}

class FirebirdStmt {
    protected $connection;
    protected $query;
    protected $params;

    public function __construct($connection, $query) {
        $this->connection = $connection;
        $this->query = $query;
        $this->params = array();
    }

    function __destruct() {
        ibase_free_query($this->query);
    }

    // Warning: only accepts numbered $sql_params
    public function bindParam($sql_param, &$param) {
        if ($sql_param <= 0) {
            throw new FirebirdException('Positional numbering starts at 1',
                5);
        }
        $this->params[$sql_param-1] = $param;
    }

    // Warning: only accepts numbered $sql_params
    public function bindValue($sql_param, $value) {
        if ($sql_param <= 0) {
            throw new FirebirdException('Positional numbering starts at 1',
                5);
        }
        $this->params[$sql_param-1] = $value;
    }

    // Clear all bound parameters/values
    public function clear() {
        $this->params = array();
    }

    public function execute() {
        return $this->executeArray($this->params);
    }

    public function executeArray($values) {
        array_unshift($values, $this->query);
        $result = @call_user_func_array('ibase_execute', $values);
        if ($result === FALSE) {
            throw new FirebirdDriverException(ibase_errmsg(), ibase_errcode());
        } else if ($result === TRUE) {
            return TRUE;
        } else if (is_resource($result)) {
            return new FirebirdResult($this->connection, $result);
        }
        return $result;
    }

    public function executeVarArgs() {
        return $this->executeArray(func_get_args());
    }

    public function numParams() {
        return ibase_num_params($this->query);
    }

    public function paramInfo($param_number) {
        return ibase_param_info($this->query, $param_number);
    }
}

class FirebirdResult {
    protected $connection;
    protected $result;
    protected $blob_columns;
    protected $blob_column_names;

    public function __construct($connection, $result) {
        $this->connection = $connection;
        $this->result = $result;
        $this->blob_columns = array();
        $this->blob_column_names = array();
        $num_fields = $this->numFields();
        for ($i = 0; $i < $num_fields; $i++) {
            $field_info = $this->fieldInfo($i);
            if ($field_info['type'] === 'BLOB') {
                $this->blob_columns[] = $i;
                $this->blob_column_names[] = $field_info['alias'];
            }
        }
    }

    function __destruct() {
        ibase_free_result($this->result);
    }

    public function fetchAssoc($fetch_flag=0, $open_blobs=TRUE) {
        $array = @ibase_fetch_assoc($this->result, $fetch_flag);
        if ($open_blobs === TRUE && ($fetch_flag & IBASE_FETCH_BLOBS) === 0
            && $array !== FALSE) {
            foreach ($this->blob_column_names as $blob_column_name) {
                if (!is_null($array[$blob_column_name])) {
                    $array[$blob_column_name] = new FirebirdBlob(
                        $this->connection, $array[$blob_column_name]);
                }
            }
        } else if ($array === FALSE) {
            $errcode = ibase_errcode();
            if ($errcode !== FALSE) {
                throw new FirebirdDriverException(ibase_errmsg(), $errcode);
            }
        }
        return $array;
    }

    public function fetchObject($fetch_flag=0, $open_blobs=TRUE) {
        $object = @ibase_fetch_object($this->result, $fetch_flag);
        if ($open_blobs === TRUE && ($fetch_flag & IBASE_FETCH_BLOBS) === 0
            && $object !== FALSE) {
            foreach ($this->blob_column_names as $blob_column_name) {
                if (!is_null($object->{$blob_column_name})) {
                    $object->{$blob_column_name} = new FirebirdBlob(
                        $this->connection, $object->{$blob_column_name});
                }
            }
        } else if ($object === FALSE) {
            $errcode = ibase_errcode();
            if ($errcode !== FALSE) {
                throw new FirebirdDriverException(ibase_errmsg(), $errcode);
            }
        }
        return $object;
    }

    public function fetchRow($fetch_flag=0, $open_blobs=TRUE) {
        $array = @ibase_fetch_row($this->result, $fetch_flag);
        if ($open_blobs === TRUE && ($fetch_flag & IBASE_FETCH_BLOBS) === 0
            && $array !== FALSE) {
            foreach ($this->blob_columns as $blob_column) {
                if (!is_null($array[$blob_column])) {
                    $array[$blob_column] = new FirebirdBlob($this->connection,
                        $array[$blob_column]);
                }
            }
        } else if ($array === FALSE) {
            $errcode = ibase_errcode();
            if ($errcode !== FALSE) {
                throw new FirebirdDriverException(ibase_errmsg(), $errcode);
            }
        }
        return $array;
    }

    public function fieldInfo($field_number) {
        return ibase_field_info($this->result, $field_number);
    }

    public function nameResult($name) {
        $success = @ibase_name_result($this->result, $name);
        if ($success === FALSE) {
            throw new FirebirdDriverException(ibase_errmsg(), ibase_errcode());
        }
        return TRUE;
    }

    public function numFields() {
        return ibase_num_fields($this->result);
    }
}

class FirebirdBlob {
    protected $connection;
    protected $blob_id;
    protected $for_writing;
    protected $blob_handle;

    public function __construct($connection, $blob_id, $for_writing=FALSE) {
        $this->connection = $connection;
        $this->for_writing = $for_writing;
        if ($for_writing === FALSE) {
            $this->blob_id = $blob_id;
            $this->blob_handle = @ibase_blob_open($connection, $blob_id);
            if ($this->blob_handle === FALSE) {
                throw new FirebirdDriverException(ibase_errmsg(),
                    ibase_errcode());
            }
        } else {
            $this->blob_handle = $blob_id;
        }
    }

    public function __destruct() {
        if (!is_null($this->blob_handle)) {
            if ($this->for_writing) {
                ibase_blob_cancel($this->blob_handle);
            } else {
                ibase_blob_close($this->blob_handle);
            }
        }
    }

    public function add($data) {
        if ($this->for_writing === FALSE) {
            throw new FirebirdException('Blob was not opened for writing',
                2);
        }
        if (is_null($this->blob_handle)) {
            throw new FirebirdException(
                'Blob was already closed/discarded', 3);
        }
        ibase_blob_add($this->blob_handle, $data);
    }

    public function cancel() {
        if (is_null($this->blob_handle)) {
            throw new FirebirdException(
                'Blob was already closed/discarded', 3);
        }
        $success = @ibase_blob_cancel($this->blob_handle);
        if ($success === FALSE) {
            throw new FirebirdDriverException(ibase_errmsg(), ibase_errcode());
        }
        $this->blob_handle = NULL;
        return $success;
    }

    public function close() {
        if (is_null($this->blob_handle)) {
            throw new FirebirdException(
                'Blob was already closed/discarded', 3);
        }
        $value = @ibase_blob_close($this->blob_handle);
        if ($value === FALSE) {
            throw new FirebirdDriverException(ibase_errmsg(), ibase_errcode());
        }
        if ($this->for_writing) {
            $this->blob_id = $value;
        }
        $this->blob_handle = NULL;
        return $value;
    }

    public function echo() {
        if ($this->for_writing === TRUE) {
            throw new FirebirdException('Blob was not opened for reading',
                4);
        }
        if (@ibase_blob_echo($this->connection, $this->blob_id) === FALSE) {
            throw new FirebirdDriverException(ibase_errmsg(), ibase_errcode());
        }
        return TRUE;
    }

    public function get($len=0) {
        if ($this->for_writing === TRUE) {
            throw new FirebirdException('Blob was not opened for reading',
                4);
        }
        if ($len <= 0) {
            $len = $this->info()["length"];
        }
        $data = @ibase_blob_get($this->blob_handle, $len);
        if ($data === FALSE) {
            throw new FirebirdDriverException(ibase_errmsg(), ibase_errcode());
        }
        return $data;
    }

    public function getId() {
        return $this->blob_id;
    }

    public function info() {
        if ($this->for_writing === TRUE) {
            throw new FirebirdException('Blob was not opened for reading',
                4);
        }
        $info = @ibase_blob_info($this->connection, $this->blob_id);
        if ($info === FALSE) {
            throw new FirebirdDriverException(ibase_errmsg(), ibase_errcode());
        }
        return $info;
    }

    public function isClosed() {
        return is_null($this->blob_handle);
    }
}

class FirebirdEventHandler {
    protected $event_handler;

    public function __construct($event_handler) {
        $this->event_handler = $event_handler;
    }

    public function freeEventHandler() {
        if (!is_null($this->event_handler)) {
            if (ibase_free_event_handler($this->event_handler) === TRUE) {
                $this->event_handdler = NULL;
                return TRUE;
            }
            return FALSE;
        }
        return TRUE;
    }

    public function isClosed() {
        return is_null($this->event_handler);
    }
}

class FirebirdService {
    protected $service_handle;

    public function __construct($host, $dba_username, $dba_password) {
        $this->service_handle = @ibase_service_attach($host, $dba_username,
            $dba_password);
        if (!is_resource($this->service_handle)) {
            $this->service_handle = NULL;
            throw new FirebirdDriverException(ibase_errmsg(), ibase_errcode());
        }
    }

    public function __destruct() {
        if ($this->service_handle !== NULL) {
            ibase_service_detach($this->service_handle);
        }
    }

    public function addUser($user_name, $password, $first_name=NULL,
        $middle_name=NULL, $last_name=NULL) {
        return ibase_add_user($this->service_handle, $user_name, $password,
            $first_name, $middle_name, $last_name);
    }

    public function backup($source_db, $dest_file, $options=0, $verbose=FALSE) {
        return ibase_backup($this->service_handle, $source_db, $dest_file,
            $options, $verbose);
    }

    public function dbInfo($db, $action, $argument=0) {
        return ibase_db_info($this->service_handle, $db, $action, $argument);
    }

    public function deleteUser($user) {
        return ibase_delete_user($this->service_handle, $user);
    }

    public function maintainDb($db, $action, $argument=0) {
        return ibase_maintain_db($this->service_handle, $db, $action,
            $argument);
    }

    public function modifyUser($user_name, $password, $first_name=NULL,
        $middle_name=NULL, $last_name=NULL) {
        return ibase_modify_user($this->service_handle, $user_name, $password,
            $first_name, $middle_name, $last_name);
    }

    public function restore($source_file, $dest_db, $options=0,
        $verbose=FALSE) {
        return ibase_restore($this->service_handle, $source_file, $dest_db,
            $options, $verbose);
    }

    public function serverInfo($action) {
        return ibase_server_info($this->service_handle, $action);
    }
}
