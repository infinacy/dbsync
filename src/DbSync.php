<?php

namespace Infinacy\DbSync;

use Infinacy\DbSync\Libraries\Connection;
use Infinacy\DbSync\Libraries\DB;
use Infinacy\DbSync\Libraries\Utility;

class DbSync {

    // @Todo Manage GENERATED columns
    // @Todo Compare each property in tables and columns, and only include if that property is changed
    // Ex: If changed table has removed the comment but previous table has one, currently comment will not be removed because of the empty condition of comment
    // Removal of comment empty condition will add unnecessary comment clause even if nothing was changed in comment
    // Solution is to compare with previous one and if different then add

    public function __construct() {
    }

    public function test() {
        echo "DB SYNC UTILITY";
    }

    public function createSyncScript($src_db, $dst_db) {

        $DB1 = new Connection($src_db);
        $DB2 = new Connection($dst_db);
        // echo $src_db . "\n";
        // echo $dst_db . "\n";

        $scripts = [];

        $prodedures1 = $DB1->getProcedures('procedure');
        $prodedures2 = $DB2->getProcedures('procedure');

        // echo "\nProcedures1: \n";
        // print_r($prodedures1->toArray());
        // echo "\nProcedures2: \n";
        // print_r($prodedures2->toArray());

        $missing_procedures = $prodedures1->map(fn($item) => Utility::serializeAndCleanup($item))->diff($prodedures2->map(fn($item) => Utility::serializeAndCleanup($item)))->map(fn($item) => Utility::unserialize($item))->values();
        $extra_procedures = $prodedures2->map(fn($item) => Utility::serializeAndCleanup($item))->diff($prodedures1->map(fn($item) => Utility::serializeAndCleanup($item)))->map(fn($item) => Utility::unserialize($item))->values();

        // echo "\nMissing procedures: \n";
        // print_r($missing_procedures->toArray());
        // echo "\nExtra procedures: \n";
        // print_r($extra_procedures->toArray());

        foreach ($extra_procedures as $proc) {
            $proc_name = $proc->Name;
            $script = "DROP PROCEDURE IF EXISTS `" . $proc_name . "`;";
            $scripts[] = $script;
        }

        foreach ($missing_procedures as $proc) {
            $scripts[] = "DELIMITER ;;";
            $scripts[] = $proc->Code . ';;';
            $scripts[] = "DELIMITER ;";
        }

        $functions1 = $DB1->getProcedures('function');
        $functions2 = $DB2->getProcedures('function');

        // echo "\nFunctions1: \n";
        // print_r($functions1->toArray());
        // echo "\nFunctions2: \n";
        // print_r($functions2->toArray());

        $missing_functions = $functions1->map(fn($item) => Utility::serializeAndCleanup($item))->diff($functions2->map(fn($item) => Utility::serializeAndCleanup($item)))->map(fn($item) => Utility::unserialize($item))->values();
        $extra_functions = $functions2->map(fn($item) => Utility::serializeAndCleanup($item))->diff($functions1->map(fn($item) => Utility::serializeAndCleanup($item)))->map(fn($item) => Utility::unserialize($item))->values();

        // echo "\nMissing functions: \n";
        // print_r($missing_functions->toArray());
        // echo "\nExtra functions: \n";
        // print_r($extra_functions->toArray());

        foreach ($extra_functions as $proc) {
            $proc_name = $proc->Name;
            $script = "DROP FUNCTION IF EXISTS `" . $proc_name . "`;";
            $scripts[] = $script;
        }

        foreach ($missing_functions as $proc) {
            $scripts[] = "DELIMITER ;;";
            $scripts[] = $proc->Code . ';;';
            $scripts[] = "DELIMITER ;";
        }

        $tables1 = $DB1->getTables();

        $tables2 = $DB2->getTables();

        $extra_tables = $tables2->map(fn($item) => Utility::serializeAndCleanup($item))->diff($tables1->map(fn($item) => Utility::serializeAndCleanup($item)))->map(fn($item) => Utility::unserialize($item))->values();

        $missing_tables = $tables1->map(fn($item) => Utility::serializeAndCleanup($item))->diff($tables2->map(fn($item) => Utility::serializeAndCleanup($item)))->map(fn($item) => Utility::unserialize($item))->values();

        $existing_tables = $tables1->map(fn($item) => Utility::serializeAndCleanup($item))->intersect($tables2->map(fn($item) => Utility::serializeAndCleanup($item)))->map(fn($item) => Utility::unserialize($item))->values();

        // echo "\nExtra Tables:";
        // print_r($extra_tables->toArray());
        foreach ($extra_tables as $table) {
            $scripts[] = "DROP TABLE IF EXISTS `" . $table->name . "`;";
        }
        // echo "\nMissing Tables:";
        // print_r($missing_tables->toArray());

        foreach ($missing_tables as $table) {
            $code = $DB1->getTableCreateCode($table);

            $scripts[] = $code;

            $triggers = $DB1->getTriggers($table->name);
            foreach ($triggers as $trig) {
                $scripts[] = "DELIMITER ;;";
                $scripts[] = $trig->Code . ';;';
                $scripts[] = "DELIMITER ;";
            }
        }

        //Important to use, because droping and recreating a renamed table will cause loss of data, renaming won't
        $potential_renamed_tables = $missing_tables->filter(function ($item) use ($DB1, $DB2, $extra_tables) {
            $show_create_1 = str_replace([$item->name, "\n", " "], '', $DB1->getTableCreateCode($item));
            // echo $show_create_1 . "\n";
            $exists = $extra_tables->filter(function ($elem) use ($DB2, $show_create_1) {
                $show_create_2 = str_replace([$elem->name, "\n", " "], '', $DB2->getTableCreateCode($elem));
                // echo $show_create_2 . "\n";
                return $show_create_1 == $show_create_2;
            });
            if ($exists->count()) {
                $item->renamedTo = $exists->toArray();
                return true;
            } else {
                return false;
            }
        })->values();

        // echo "\nPotentially Renamed Tables:";
        // print_r($potential_renamed_tables->toArray());

        foreach ($existing_tables as $table) {
            $tablename = $table->name;
            // echo "\nTable: " . $tablename . "\n";

            $columns2 = $DB2->getColumns($table);
            $indexes1 = $DB1->getIndexes($table);
            $indexes2 = $DB2->getIndexes($table);

            $foreign_keys1 = $DB1->getForeignKeys($table);
            $foreign_keys2 = $DB2->getForeignKeys($table);
            $table_options1 = $DB1->getTableOptions($table);
            $table_options2 = $DB2->getTableOptions($table);
            $triggers1 = $DB1->getTriggers($table);
            $triggers2 = $DB2->getTriggers($table);

            // print_r($table_options1);
            // print_r($table_options2);

            // echo "\nForeign Keys: \n";
            // print_r($foreign_keys1->toArray());
            // print_r($foreign_keys2->toArray());
            $missing_foreign_keys = $foreign_keys1->map(fn($item) => Utility::serializeAndCleanup($item))->diff($foreign_keys2->map(fn($item) => Utility::serializeAndCleanup($item)))->map(fn($item) => Utility::unserialize($item))->values();
            $extra_foreign_keys = $foreign_keys2->map(fn($item) => Utility::serializeAndCleanup($item))->diff($foreign_keys1->map(fn($item) => Utility::serializeAndCleanup($item)))->map(fn($item) => Utility::unserialize($item))->values();

            // echo "\nMissing foreign keys: \n";
            // print_r($missing_foreign_keys->toArray());
            // echo "\nExtra foreign keys: \n";
            // print_r($extra_foreign_keys->toArray());

            foreach ($extra_foreign_keys as $key) {
                $key_name = $key->CONSTRAINT;
                $script = "ALTER TABLE `" . $tablename . "` DROP FOREIGN KEY `" . $key_name . "`;";
                $scripts[] = $script;
            }


            $columns1 = $DB1->getColumns($table);
            $columns1_positions = $columns1->map(function ($val, $key) use ($columns1) {
                $clone = clone ($val);
                if ($key > 0) {
                    $clone->before = $columns1[$key - 1]->Field;
                }
                return $clone;
            })->keyBy('Field');

            $missing_columns = $columns1->map(fn($item) => Utility::serializeAndCleanup($item))->diff($columns2->map(fn($item) => Utility::serializeAndCleanup($item)))->map(fn($item) => Utility::unserialize($item))->values();
            // echo "\nMissing columns: \n";
            // print_r($missing_columns->toArray());
            $extra_columns = $columns2->map(fn($item) => Utility::serializeAndCleanup($item))->diff($columns1->map(fn($item) => Utility::serializeAndCleanup($item)))->map(fn($item) => Utility::unserialize($item))->values();

            $existing_columns = $columns1->map(fn($item) => Utility::serializeAndCleanup($item))->intersect($columns2->map(fn($item) => Utility::serializeAndCleanup($item)))->map(fn($item) => Utility::unserialize($item))->values();

            $extra_columns_only_names = $extra_columns->map(function ($item) {
                return $item->Field;
            });

            $changed_columns = $missing_columns->filter(function ($item) use ($extra_columns_only_names, $extra_columns) {
                $exists = $extra_columns_only_names->search($item->Field);
                if ($exists !== false) {
                    $item->renamedTo = $extra_columns[$exists];
                    return true;
                } else {
                    return false;
                }
            });

            // echo "\nExtra foreign keys: \n";
            // print_r($extra_foreign_keys->toArray());

            $changed_columns_only_names = $changed_columns->map(function ($item) {
                return $item->Field;
            });

            //Remove changed columns from extra columns list, since its changed not added
            $extra_columns = $extra_columns->filter(function ($item) use ($changed_columns_only_names) {
                $exists = $changed_columns_only_names->search($item->Field);
                return ($exists === false); //Take only those which doesn't exist in changed column list
            });

            //Remove changed columns from missing columns list, since its changed not missing
            $missing_columns = $missing_columns->filter(function ($item) use ($changed_columns_only_names) {
                $exists = $changed_columns_only_names->search($item->Field);
                return ($exists === false); //Take only those which doesn't exist in changed column list
            });

            // echo "\nExtra columns: \n";
            // print_r($extra_columns->toArray());

            foreach ($extra_columns as $col) {
                $scripts[] = "ALTER TABLE `" . $tablename . "` DROP COLUMN `" . $col->Field . "`;";
            }

            if ($changed_columns->count()) {
                // echo "\nChanged columns: \n";
                // print_r($changed_columns->toArray());
            }

            foreach ($missing_columns as $col) {
                $script = "ALTER TABLE `" . $tablename . "` ADD COLUMN `" . $col->Field . "` " . $col->Type;
                if ($col->Collation) {
                    $script .= " CHARACTER SET " . substr($col->Collation, 0, strpos($col->Collation, '_') !== false ? strpos($col->Collation, '_') : null);
                    $script .= " COLLATE " . $col->Collation;
                }

                if ($col->Null == 'YES') {
                    $script .= " NULL";
                } else {
                    $script .= " NOT NULL";
                }

                $script = Utility::handleDefaultValue($col, $script);

                if ($col->Extra) {
                    $script .= " " . $col->Extra;
                }

                if ($col->Comment) {
                    $script .= " COMMENT '" . addslashes($col->Comment) . "'";
                }

                if ($columns1_positions->has($col->Field) and @$columns1_positions[$col->Field]->before) {
                    $script .= " AFTER `" . $columns1_positions[$col->Field]->before . "`";
                }

                $script .= ";";
                $scripts[] = $script;
            }

            // echo "\nExisting columns: \n";
            // print_r($existing_columns->toArray());

            // echo "\nChanged columns: \n";
            // print_r($changed_columns->toArray());

            foreach ($changed_columns as $col) {
                // $script = "ALTER TABLE `" . $tablename . "` CHANGE COLUMN `" . $col->Field . "` `" . $col->Field . "` " . $col->Type; //with renaming
                $script = "ALTER TABLE `" . $tablename . "` MODIFY COLUMN `" . $col->Field . "` " . $col->Type; //Witout renaming
                if ($col->Collation) {
                    $script .= " CHARACTER SET " . substr($col->Collation, 0, strpos($col->Collation, '_') !== false ? strpos($col->Collation, '_') : null);
                    $script .= " COLLATE " . $col->Collation;
                }

                if ($col->Null == 'YES') {
                    $script .= " NULL";
                } else {
                    $script .= " NOT NULL";
                }

                $script = Utility::handleDefaultValue($col, $script);

                // if ($col->Extra) {
                //     $script .= " " . $col->Extra;
                // }

                if ($col->Comment) {
                    $script .= " COMMENT '" . addslashes($col->Comment) . "'";
                }

                if ($columns1_positions->has($col->Field) and @$columns1_positions[$col->Field]->before) {
                    $script .= " AFTER `" . $columns1_positions[$col->Field]->before . "`";
                }

                $script .= ";";
                $scripts[] = $script;
            }

            //Find other column definitions except for the name, to find matches for potential renames.
            //If type and other info are same but the name, then it could be a potential rename
            $extra_columns_without_names = $extra_columns->map(function ($item) {
                $clone = clone ($item);
                unset($clone->Field);
                unset($clone->Comment);
                return Utility::serializeAndCleanup($clone);
            });

            $potential_renamed_columns = $missing_columns->filter(function ($item) use ($extra_columns_without_names, $extra_columns) {
                $clone = clone ($item);
                unset($clone->Field);
                unset($clone->Comment);
                $old_field_without_name = Utility::serializeAndCleanup($clone);
                $exists = $extra_columns_without_names->filter(function ($elem) use ($old_field_without_name) {
                    return $elem == $old_field_without_name;
                })->map((function ($elem, $index) use ($extra_columns) {
                    return $extra_columns[$index];
                }));
                if ($exists->count()) {
                    $item->renamedTo = $exists->toArray();
                    return true;
                } else {
                    return false;
                }
            });

            // echo "\nPotentially renamed columns: \n";
            // print_r($potential_renamed_columns->toArray());

            // echo "\nIndexes: \n";
            // print_r($indexes1->toArray());
            // print_r($indexes2->toArray());

            $missing_indexes = $indexes1->map(fn($item) => Utility::serializeAndCleanup($item))->diff($indexes2->map(fn($item) => Utility::serializeAndCleanup($item)))->map(fn($item) => Utility::unserialize($item))->values();

            $extra_indexes = $indexes2->map(fn($item) => Utility::serializeAndCleanup($item))->diff($indexes1->map(fn($item) => Utility::serializeAndCleanup($item)))->map(fn($item) => Utility::unserialize($item))->values();

            // echo "\nMissing indexes: \n";
            // print_r($missing_indexes->toArray());
            // echo "\nExtra indexes: \n";
            // print_r($extra_indexes->toArray());

            if (count($extra_indexes) or count($missing_indexes)) {
                $script = "ALTER TABLE `" . $tablename . "` ";
                $index_scripts = [];
                foreach ($extra_indexes as $indexes) {
                    $key_name = $indexes[0]->Key_name;
                    if ($key_name == 'PRIMARY') {
                        $tmpscript = "DROP PRIMARY KEY";
                    } else {
                        $tmpscript = "DROP INDEX `" . $key_name . "`";
                    }
                    $index_scripts[] = $tmpscript;
                }

                foreach ($missing_indexes as $indexes) {
                    $first_key = $indexes[0];
                    $indexes = collect($indexes);
                    $key_name = $first_key->Key_name;
                    if ($key_name == 'PRIMARY') {
                        $tmpscript = "ADD PRIMARY KEY(" . $indexes->map(fn($item) => "`" . $item->Column_name . "`" . ($item->Sub_part ? "(" . $item->Sub_part . ")" : ""))->join(",") . ") ";
                    } else {
                        $tmpscript = "ADD ";
                        if ($first_key->Non_unique == 0) {
                            $tmpscript .= "UNIQUE ";
                        }
                        if ($first_key->Index_type == 'FULLTEXT') {
                            $tmpscript .= "FULLTEXT ";
                        }
                        $tmpscript .= "INDEX `" . $key_name . "`(" . $indexes->map(fn($item) => "`" . $item->Column_name . "`" . ($item->Sub_part ? "(" . $item->Sub_part . ")" : ""))->join(",") . ") ";
                    }
                    if ($first_key->Index_type != 'FULLTEXT') {
                        $tmpscript .=  "USING " . $first_key->Index_type . " ";
                    }

                    if ($first_key->Index_comment) {
                        $tmpscript .=  "COMMENT '" . addslashes($first_key->Index_comment) . "'";
                    }
                    $tmpscript = trim($tmpscript);
                    $index_scripts[] = $tmpscript;
                }
                $script .= collect($index_scripts)->join(', ');
                $script .= ";";
                $scripts[] = $script;
            }


            //This part is needed twice. Once before indexes (omitting the auto increment part) and once after indexes
            //Because sometimes indexes require the column type to be updated first
            //On the other hand, auto increment requires index to be set first
            //So we update the column withut A_I, then put indexes, then put A_I
            foreach ($changed_columns as $col) {
                // $script = "ALTER TABLE `" . $tablename . "` CHANGE COLUMN `" . $col->Field . "` `" . $col->Field . "` " . $col->Type; //with renaming
                $script = "ALTER TABLE `" . $tablename . "` MODIFY COLUMN `" . $col->Field . "` " . $col->Type; //Witout renaming
                if ($col->Collation) {
                    $script .= " CHARACTER SET " . substr($col->Collation, 0, strpos($col->Collation, '_') !== false ? strpos($col->Collation, '_') : null);
                    $script .= " COLLATE " . $col->Collation;
                }

                if ($col->Null == 'YES') {
                    $script .= " NULL";
                } else {
                    $script .= " NOT NULL";
                }

                $script = Utility::handleDefaultValue($col, $script);

                if ($col->Extra) {
                    $script .= " " . $col->Extra;
                }

                if ($col->Comment) {
                    $script .= " COMMENT '" . addslashes($col->Comment) . "'";
                }

                if ($columns1_positions->has($col->Field) and @$columns1_positions[$col->Field]->before) {
                    $script .= " AFTER `" . $columns1_positions[$col->Field]->before . "`";
                }

                $script .= ";";
                $scripts[] = $script;
            }

            foreach ($missing_foreign_keys as $key) {
                $script = "ALTER TABLE `" . $tablename . "` ADD " . trim($key->LINE) . ";";
                $scripts[] = $script;
            }

            $table_options1 = collect([
                "ENGINE" => $table_options1->Engine,
                "ROW_FORMAT" => strtoupper($table_options1->Row_format),
                // "AUTO_INCREMENT" => $table_options1->Auto_increment,
                "COLLATE" => $table_options1->Collation,
                "COMMENT" => "'" . $table_options1->Comment . "'"
            ]);
            $table_options2 = collect([
                "ENGINE" => $table_options2->Engine,
                "ROW_FORMAT" => strtoupper($table_options2->Row_format),
                // "AUTO_INCREMENT" => $table_options2->Auto_increment,
                "COLLATE" => $table_options2->Collation,
                "COMMENT" => "'" . $table_options2->Comment . "'"
            ]);

            // echo "\nTable Options1: $tablename\n";
            // print_r($table_options1);
            // echo "\nTable Options2: \n";
            // print_r($table_options2);

            //No need to find missing or extra, since tables are the same
            $changed_table_options = $table_options1->diff($table_options2);

            // echo "\nChanged table options: \n";
            // print_r($changed_table_options->toArray());

            if ($changed_table_options->count()) {
                $script = "ALTER TABLE `" . $tablename . "` ";
                $script .= $changed_table_options->map(fn($val, $key) => $key . '=' . $val)->join(' ');
                $script .= ";";
                $scripts[] = $script;
            }

            // echo "\nTriggers1: \n";
            // print_r($triggers1->toArray());
            // echo "\nTriggers2: \n";
            // print_r($triggers2->toArray());
            $missing_triggers = $triggers1->map(fn($item) => Utility::serializeAndCleanup($item))->diff($triggers2->map(fn($item) => Utility::serializeAndCleanup($item)))->map(fn($item) => Utility::unserialize($item))->values();
            $extra_triggers = $triggers2->map(fn($item) => Utility::serializeAndCleanup($item))->diff($triggers1->map(fn($item) => Utility::serializeAndCleanup($item)))->map(fn($item) => Utility::unserialize($item))->values();

            // echo "\nMissing triggers: \n";
            // print_r($missing_triggers->toArray());
            // echo "\nExtra triggers: \n";
            // print_r($extra_triggers->toArray());

            foreach ($extra_triggers as $trig) {
                $trig_name = $trig->Name;
                $script = "DROP TRIGGER IF EXISTS `" . $trig_name . "`;";
                $scripts[] = $script;
            }

            foreach ($missing_triggers as $trig) {
                $scripts[] = "DELIMITER ;;";
                $scripts[] = $trig->Code . ';;';
                $scripts[] = "DELIMITER ;";
            }
        }


        $views1 = $DB1->getViews();
        $views2 = $DB2->getViews();

        // echo "\nViews 1:";
        // print_r($views1->toArray());
        // echo "\nViews 2:";
        // print_r($views2->toArray());


        $extra_views = $views2->map(fn($item) => Utility::serializeAndCleanup($item))->diff($views1->map(fn($item) => Utility::serializeAndCleanup($item)))->map(fn($item) => Utility::unserialize($item))->values();

        $missing_views = $views1->map(fn($item) => Utility::serializeAndCleanup($item))->diff($views2->map(fn($item) => Utility::serializeAndCleanup($item)))->map(fn($item) => Utility::unserialize($item))->values();

        // echo "\nExtra Views:";
        // print_r($extra_views->toArray());
        // echo "\nMissing Views:";
        // print_r($missing_views->toArray());
        foreach ($extra_views as $view) {
            $scripts[] = "DROP VIEW IF EXISTS `" . $view->name . "`;";
        }
        $new_view_scripts = [];
        foreach ($missing_views as $view) {
            $new_view_scripts[] = ['name' => $view->name, 'code' => $view->code . ";"];
        }

        foreach ($new_view_scripts as $key => $a) {
            $new_view_scripts[$key]['before'] = [];
            foreach ($new_view_scripts as $b) {
                if ($a['name'] == $b['name']) continue;
                $a_referring_b = strpos($a['code'], $b['name']) !== false;
                if ($a_referring_b) {
                    $new_view_scripts[$key]['before'][] = $b['name'];
                }
            }
        }

        $final_views = [];
        while (count($new_view_scripts)) {
            $current = array_shift($new_view_scripts);
            if (count(array_diff($current['before'], array_keys($final_views)))) {
                //Other depending views still pending, defere this one
                array_push($new_view_scripts, $current);
            } else {
                $final_views[$current['name']] = $current['code'];
            }
        }

        // print_r($final_views);

        $scripts = array_merge($scripts, array_values($final_views));

        array_unshift($scripts, 'SET FOREIGN_KEY_CHECKS=0;');
        array_push($scripts, 'SET FOREIGN_KEY_CHECKS=1;');

        foreach ($scripts as $script) {
            echo $script;
            echo "\n";
        }
    }
}
