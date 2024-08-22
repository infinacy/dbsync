<?php

namespace Infinacy\DbSync\Libraries;

class Connection {

    private $connection;

    public function __construct($dbname) {
        $dbconfig = (new Config)->getConfig('db');
        $DB = DB::instance();
        if (!$DB) {
            $DB = DB::init();
        }
        $DB->addConnection([
            "driver" => "mysql",
            "host" => $dbconfig->host,
            "username" => $dbconfig->username,
            "password" => $dbconfig->password,
            "database" => $dbname,
        ], $dbname);
        $this->connection = $DB->connection($dbname);
    }

    public function connection() {
        return $this->connection;
    }

    public function getTables() {
        $dbname = $this->connection()->getDatabaseName();
        $list = collect($this->connection()->select("SHOW FULL TABLES WHERE table_type = 'BASE TABLE'"))
            ->map(function ($item, $index) use ($dbname) {
                return (object)['name' => $item->{'Tables_in_' . $dbname}];
            });
        return $list;
    }

    public function getTableCreateCode($table) {
        $code = $this->connection()->select("SHOW CREATE TABLE " . $table->name)[0]->{"Create Table"};
        $code = preg_replace("/ AUTO_INCREMENT=(.+?)\b/u", '', $code);
        // $code = str_replace(["\n"], '', $code);
        // $code = str_replace(["\n"], "\n\n", $code);
        $code = $code . ';';
        return $code;
    }

    public function getColumns($table) {
        // $list = collect($this->connection()->select("DESCRIBE " . $table->name)); //Doesn't include comments and privileges
        $list = collect($this->connection()->select("SHOW FULL COLUMNS FROM " . $table->name))->map(function ($item) {
            $item->RealType = Utility::getRealType($item->Type);
            return $item;
        });
        return $list;
    }

    public function getIndexes($table) {
        $list = collect($this->connection()->select("SHOW INDEXES FROM " . $table->name))->map(function ($item) {
            unset($item->Cardinality);
            return $item;
        })->groupBy('Key_name')->values();
        return $list;
    }

    public function getViews() {
        $dbname = $this->connection()->getDatabaseName();
        $list = collect($this->connection()->select("SHOW FULL TABLES WHERE table_type = 'VIEW'"))
            ->map(function ($item, $index) use ($dbname) {
                $name = $item->{'Tables_in_' . $dbname};
                $code = $this->connection()->select("SHOW CREATE TABLE " . $name)[0]->{'Create View'};
                $code = preg_replace("/(?=ALGORITHM=)(.+?)(?= VIEW) /u", '', $code);
                $code = str_replace("\r\n", "\n", $code);
                $code = collect(explode("\n", $code))->filter(fn($item) => strlen(trim($item)))->join("\n");
                return (object)['name' => $name, 'code' => $code];
            });
        return $list;
    }

    public function getForeignKeys($table) {
        $ctCommand = $this->connection()->select("SHOW CREATE TABLE  " . $table->name);
        // echo $ctCommand[0]->{"Create Table"};
        $lines = collect(explode("\n", $ctCommand[0]->{"Create Table"}))->filter(function ($item) {
            return strpos(trim($item), 'CONSTRAINT') === 0;
        })->values()->map(function ($item) {
            $item = trim($item, ' ,');
            $return = ['LINE' => $item];
            $re = '/\b(ON DELETE|ON UPDATE|FOREIGN KEY|CONSTRAINT|REFERENCES)\b(.+?)(?=ON DELETE|ON UPDATE|FOREIGN KEY|CONSTRAINT|REFERENCES|$)/u';
            preg_match_all($re, $item, $matches);
            $return = array_merge($return, array_combine($matches[1], $matches[2]));
            $return['ON UPDATE'] = @$return['ON UPDATE'] ? trim($return['ON UPDATE']) : null;
            $return['ON DELETE'] = @$return['ON DELETE'] ? trim($return['ON DELETE']) : null;
            $return['REFERENCES'] = @$return['REFERENCES'] ? (object)collect(explode(' ', trim($return['REFERENCES'])))->map(function ($item) {
                return trim($item, ' `()');
            })->toArray() : null;
            $return['FOREIGN KEY'] = @$return['FOREIGN KEY'] ? trim($return['FOREIGN KEY'], ' `)(') : null;
            $return['CONSTRAINT'] = @$return['CONSTRAINT'] ? trim($return['CONSTRAINT'], ' `)(') : null;
            return (object)($return);
        });
        return $lines;
    }

    public function getProcedures($type = 'procedure') {
        $dbname = $this->connection()->getDatabaseName();
        $type = strtoupper($type);
        $list = collect($this->connection()->select("SHOW " . $type . " STATUS WHERE Db='" . $dbname . "'"))->map(function ($item) use ($type) {
            $func = $this->connection()->select("SHOW CREATE " . $item->Type . " `" . $item->Name . "`")[0];
            $code = preg_replace("/(?=DEFINER=)(.+?)(?= " . $type . ") /u", '', $func->{"Create " . ucfirst(strtolower($item->Type))});
            $code = str_replace("\r\n", "\n", $code);
            $code = collect(explode("\n", $code))->filter(fn($item) => strlen(trim($item)))->join("\n");
            return (object)['Name' => $item->Name, 'Code' => $code];
        });

        return $list;
    }

    public function getTriggers($table) {
        $list = collect($this->connection()->select("SHOW TRIGGERS WHERE `Table`='" . $table->name . "'"))->map(function ($item) {
            $trig = $this->connection()->select("SHOW CREATE TRIGGER `" . $item->Trigger . "`")[0];
            $code = preg_replace("/(?=DEFINER=)(.+?)(?= TRIGGER) /u", '', $trig->{"SQL Original Statement"});
            $code = str_replace("\r\n", "\n", $code);
            $code = collect(explode("\n", $code))->filter(fn($item) => strlen(trim($item)))->join("\n");
            return (object)['Name' => $item->Trigger, 'Event' => $item->Event, 'Table' => $item->Table, 'Timing' => $item->Timing, 'Code' => $code];
        });
        return $list;
    }

    public function getEvents($tablename) {
        //@Todo complete and implement
        $list = collect($this->connection()->select("SHOW EVENTS"))->map(function ($item) {
            $trig = $this->connection()->select("SHOW CREATE EVENT `" . $item->event . "`")[0];
            $code = preg_replace("/(?=DEFINER=)(.+?)(?= EVENT) /u", '', $trig->{"SQL Original Statement"});
            $code = str_replace("\r\n", "\n", $code);
            $code = collect(explode("\n", $code))->filter(fn($item) => strlen(trim($item)))->join("\n");
            return (object)['Name' => $item->Trigger, 'Event' => $item->Event, 'Table' => $item->Table, 'Timing' => $item->Timing, 'Code' => $code];
        });
        return $list;
    }

    public function getTableOptions1($table) {
        $ctCommand = $this->connection()->select("SHOW CREATE TABLE " . $table->name);
        $lines = collect(explode("\n", $ctCommand[0]->{"Create Table"}))->filter(function ($item) {
            return strpos(trim($item, ' ()`'), 'ENGINE') === 0;
        })->values()->map(function ($item) {
            $item = trim($item, ' ,');
            $return = ['LINE' => $item];
            $re = '/\b(ENGINE|AUTO_INCREMENT|DEFAULT CHARSET|ROW_FORMAT|COMMENT)=(.+?)(?=ENGINE|AUTO_INCREMENT|DEFAULT CHARSET|ROW_FORMAT|COMMENT|$)/u';
            preg_match_all($re, $item, $matches);
            $matches[2] = array_map(fn($item) => trim($item), $matches[2]);
            $return = array_merge($return, array_combine($matches[1], $matches[2]));
            return (object)($return);
        });
        return $lines;
    }

    public function getTableOptions($table) {
        $ctCommand = $this->connection()->select("SHOW TABLE STATUS LIKE '" . $table->name . "';");
        // print_r($ctCommand);
        return $ctCommand[0];
    }
}
