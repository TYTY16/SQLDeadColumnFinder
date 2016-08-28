<?php

class SQLDeadColumnFinder1
{
    /**
     * @param string $db
     * @param int $months
     * @param boolean $all
     * @param int $numColumns
     * @param PDO $db
     */
    private $dbName, $months, $all, $numColumns, $db;

    /**
     * Create a new instance of SQLDeadColumnFinder.
     *
     * @param PDO $db [ A PDO connection instance to the database ]
     * @param string $dbName [ The name of the database to check ]
     * @param bool $all [ Whether to check tables without a created_at column ]
     *
     * @return void
     */
    public function __construct( $db, $dbName, $all = false, $months = 6, $file = 'dead-columns' ){
        $this->dbName = $dbName;
        $this->db = $db;
        $this->all = $all;
        $this->months = $months;
        $this->file = $file;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function find()
    {
        $tables = $this->getTablesToCheck();
        $columnsByTable = $this->getColumnsToCheck( $tables );
        $dbWithTablesWithColumns = $this->formatTablesWithColumns( $columnsByTable );
        $columns = $this->findDeadColumns( $dbWithTablesWithColumns );
        $this->outputToFile( $columns );

    }

    /**
     * Finds tables from information_schema that need to be checked either by date or all
     *
     * @return Array
     */
    private function getTablesToCheck()
    {
        $results = [];
        if( $this->all ){
            $stmt = $this->db->prepare( "SELECT * FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?" );
            $stmt->execute( array( $this->dbName ) );
        } else{
            $stmt = $this->db->prepare( "SELECT t.TABLE_NAME, t.TABLE_SCHEMA FROM information_schema.TABLES AS t 
                LEFT JOIN information_schema.COLUMNS AS c USING (TABLE_SCHEMA, TABLE_NAME)
                WHERE c.COLUMN_NAME = 'created_at' AND t.TABLE_SCHEMA = ? 
                GROUP BY t.TABLE_NAME, t.TABLE_SCHEMA" 
            );
            $stmt->execute( array( $this->dbName ) );
        }
        
        return $stmt->fetchAll( \PDO::FETCH_ASSOC );
    }

    /**
     * From the table results, get all the columns that need to be checked from information_schema
     *
     * @param $tables
     *
     * @return Array
     */
    private function getColumnsToCheck( $tables )
    {
        $result = [];
        $results = [];
        foreach( $tables as $table ){
            $stmt = $this->db->prepare( "SELECT TABLE_SCHEMA, TABLE_NAME, COLUMN_NAME 
                FROM information_schema.COLUMNS WHERE TABLE_NAME = ? AND TABLE_SCHEMA = ?" 
            );
            $stmt->execute( array( $table[ 'TABLE_NAME' ], $table[ 'TABLE_SCHEMA' ] ) );
            $result = $stmt->fetchAll( \PDO::FETCH_ASSOC );
            $this->numColumns += sizeof( $result );
            $results[] = $result;
        }
        return $results;
    }

    /**
     * Format the table and column results in a way that dead columns can be found in an organized and clean manner
     *
     * @param array $columnsByTable
     *
     * @return array
     */
    private function formatTablesWithColumns( $columnsByTable )
    {
        $formattedTablesWithColumns = array();
        foreach( $columnsByTable as $table ){
            foreach( $table as $column ){
                $formattedTablesWithColumns[ $column[ 'TABLE_SCHEMA' ] ][ $column[ 'TABLE_NAME' ] ][] = $column[ 'COLUMN_NAME' ];
            }
        }
        return $formattedTablesWithColumns;
    }

    /**
     * Using the collected database-table-column data, check for dead columns based on the number of distinct values or
     * null columns
     *
     * @param array $dbWithTablesWithColumns
     *
     * @return array
     */
    private function findDeadColumns( $dbWithTablesWithColumns )
    {
        $deadColumns = [];
        $date = date( 'Y-m-d H:i:s', mktime( 0, 0, 0, date( "m" ) - $this->months, date( "d" ), date( "Y" ) ) );
        foreach( $dbWithTablesWithColumns as $db => $tables ){
            foreach( $tables as $tableName => $columns ){
                foreach( $columns as $column ){
                    $selectString = "SELECT COUNT(DISTINCT(`$column`)) AS unique_values, ISNULL(`$column`) AS is_null, `$column` AS val FROM " . $tableName;
                    if( $this->all ){
                        $selectString .= " WHERE `$column` IS NOT NULL";
                        $stmt = $this->db->prepare( $selectString );
                        $stmt->execute();
                        $results = $stmt->fetchAll( \PDO::FETCH_ASSOC );
                        // $results = DB::select( $selectString );
                    } else{
                        $selectString .= " WHERE created_at >= ? AND `$column` IS NOT NULL";
                        //$results = DB::select( $selectString, [ $date ] );
                        $stmt = $this->db->prepare( $selectString );
                        $stmt->execute( array( $date ) );
                        $results = $stmt->fetchAll( \PDO::FETCH_ASSOC );
                        
                    }
                    if( $results[ 0 ][ 'unique_values' ] == 0 || $results[ 0 ][ 'unique_values' ] == 1 ){
                        $deadColumns[ $db ][ $tableName ][ $column ][ 'distinct' ] = $results[ 0 ][ 'unique_values' ];
                        $deadColumns[ $db ][ $tableName ][ $column ][ 'value' ] = $results[ 0 ][ 'val' ];
                        $deadColumns[ $db ][ $tableName ][ $column ][ 'is_null' ] = $results[ 0 ][ 'is_null' ];
                    }
                }
            }
        }
        return $deadColumns;
    }

    /**
     * Outputs the collected dead columns to a CSV file that is stored in storage/app
     *
     * @param array $deadColumns
     *
     * @return void
     */
    private function outputToFile( $deadColumns )
    {
        $this->file = $this->file . '.csv';
        $fp = fopen( $this->file, 'w' );
        $header = array( "Database", "Table", "Column", "Distinct Values", "Value", "In the past $this->months months" );
        fputcsv( $fp, $header );
        foreach( $deadColumns as $dbName => $database ){
            foreach( $database as $tableName => $table ){
                foreach( $table as $columnName => $column ){
                    $row = array( $dbName, $tableName, $columnName, $column[ 'distinct' ], ( $column[ 'is_null' ] )? "Null" : $column[ 'value' ] );
                    fputcsv( $fp, $row );
                }
            }
        }
        fclose( $fp );
    }
}
