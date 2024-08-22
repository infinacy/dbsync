<?php

namespace Infinacy\DbSync\Libraries;

class Utility {

    public static function serializeAndCleanup($object, $debug = false) {
        $str = str_replace("\\r\\n", "\\n", json_encode($object));
        if ($debug) {
            echo $str . "\n";
        }
        return $str;
    }

    public static function unserialize($str, $debug = false) {
        $object = json_decode($str);
        if ($debug) {
            print_r($object) . "\n";
        }
        return $object;
    }

    public static function getRealType($type_with_length) {
        $type = trim(substr($type_with_length, 0, strpos($type_with_length, '(')));
        return $type;
    }

    public static function handleDefaultValue($col, $script) {
        $texttypes = ['char', 'varchar', 'binary', 'varbinary', 'tinyblob', 'blob', 'mediumblob', 'longblob', 'tinytext', 'text', 'mediumtext', 'longtext', 'enum', 'set'];
        //if 0, then first condition will fail. 
        //For text types, there is a conflict between empty strings and Null(no default value), so we will set to '' (empty string) to unify
        if ($col->Default or strlen($col->Default) or in_array($col->RealType, $texttypes)) {
            // print_r($col);
            if (in_array($col->Type, ['datetime', 'date', 'timestamp', 'time']) and $col->Default == 'CURRENT_TIMESTAMP') {
                //Do not add quotes
                $script .= " DEFAULT " . $col->Default;
            } elseif (in_array($col->RealType, $texttypes)) {
                if ($col->Default === '' or $col->Default == 'EMPTY_STRING') {
                    $script .= " DEFAULT ''";
                } elseif (strlen($col->Default)) {
                    //If 0 or any other string
                    $script .= " DEFAULT '" . $col->Default . "'";
                }
            } elseif ($col->Default == 'NULL') {
                //Do not add quotes
                $script .= " DEFAULT NULL";
            } else {
                $script .= " DEFAULT '" . $col->Default . "'";
            }
        }

        return $script;
    }
}
