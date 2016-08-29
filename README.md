# SQLDeadColumnFinder

A php class designed to find "dead" columns in a SQL database (Null or only 1 unique value).  

##Installation

This library requires PHP 5.1 or later, but it is recommended to use the latest version of PHP.  It does not have any other dependencies.

It can either be autoloaded and installed via Composer [tyty16/sqldeadcolumnfinder](https://packagist.org/packages/tyty16/sqldeadcolumnfinder), or can be downloaded on its own.

##Getting Started

###Instantiation

Instantiate the SQLDeadColumnFinder class with the pdo connection along with the name of the database you would like to check.  Optional parameters include:

-**$all** (boolean value indicating whether to check all tables, or just tables with a created_at column) default:false
-**$months** (integer value for how many months prior to the created_at date to check. Used when all is set to false) default:6
-**$file** (string value for the desired output file name) default:'dead-columns'

####Example

```php

<?php

$db = new PDO('mysql:host=localhost;dbname=test', $user, $pass);
$sqlDeadColumnFinder = new SQLDeadColumnFinder($db, 'test', false, 6, 'path/to/dir/deadColumns');

?>

```

###Finding Dead Columns

Call the **find()** method, which will call the individual methods to gather the columns to check, check them, and then export the data to a csv file with either the default file name, or the given file path during instantiation.

####Example

```php

<?php

$sqlDeadColumnFinder->find();

?>

```

##Methods

Methods can be called individually, but must follow the expected array format for the input parameters.

###getTablesToCheck()

Gathers table names from INFORMATION_SCHEMA to be checked.  This will only include tables with a "created_at" column if **$all** is set to true.

####Example

```php

<?php

$tables = array();
$tables = $sqlDeadColumnFinder->getTablesToCheck();

?>

```

####Returned Array Format

```
array(2) {
  [0]=>
  array(2) {
    ["TABLE_NAME"]=>
    string(3) "foo"
    ["TABLE_SCHEMA"]=>
    string(4) "test"
  }
  [1]=>
  array(2) {
    ["TABLE_NAME"]=>
    string(3) "bar"
    ["TABLE_SCHEMA"]=>
    string(4) "test"
  }
}

```

###getColumnsToCheck($tables)

Gathers column names from INFORMATION_SCHEMA to be checked using the $tables array parameter.  **$tables** must be formatted in the same way as the returned array from **getTablesToCheck()**.  This method also increments the **$numColumns** field.

####Example

```php

<?php

$columns = $sqlDeadColumnFinder->getColumnsToCheck($tables);

?>

```

####Returned Array Format

```

array(3) {
  [0]=>
  array(3) {
    ["TABLE_SCHEMA"]=>
    string(4) "test"
    ["TABLE_NAME"]=>
    string(3) "foo"
    ["COLUMN_NAME"]=>
    string(2) "id"
  }
  [1]=>
  array(2) {
    ["TABLE_SCHEMA"]=>
    string(4) "test"
    ["TABLE_NAME"]=>
    string(3) "foo"
    ["COLUMN_NAME"]=>
    string(2) "name"
  }
  [2]=>
  array(2) {
    ["TABLE_SCHEMA"]=>
    string(4) "test"
    ["TABLE_NAME"]=>
    string(3) "bar"
    ["COLUMN_NAME"]=>
    string(2) "address"
  }
}

```

###formatTablesWithColumns($columnsByTable)

Formats columns and table names in a way where nested for loops can be used in **findDeadColumns()**.  The format for $columnsByTable should be the returned array format from **getColumnsToCheck()**.

####Example
```php

<?php
  
  $formattedTablesWithColumns = sqlDeadColumnFinder->formatTablesWithColumns($unformattedColumns);
  
?>

```

####Returned Array Format

```

array(1) {
  ["test"]=>
  array(2) {
    ["foo"]=>
    array(2) {
      [0]=>
      string(2) "id"
      [1]=>
      string(4) "name"
    }
    ["bar"]=>
    array(1) {
      [0]=>
      string(7) "address"
    }
  }
}

```

###findDeadColumns($dbWithTablesWithColumns)

Searches the given list of columns parameter and finds columns with either one unique value, or is completely null.  If the **$all** is set to false, then only rows that are recent within the number of **$months** will be included in the search.  **$dbWithTablesWithColumns** must be formatted in the same way as the return array from **formatTablesWithColumns()**.  An array will be returned with the following values for each column:

-distinct (The number of unique values for that column)
-value (The distinct value itself)
-is_null (Whether the column is completely null)

####Example

```php

<?php
  
  $deadColumns = sqlDeadColumnFinder->formatTablesWithColumns($unformattedColumns);
  
?>

```

####Returned Array Format

```

array(1) {
  ["test"]=>
  array(1) {
    ["foo"]=>
    array(2) {
      ["id"]=>
        array(3) {
          ["distinct"]=>
          int(1)
          ["value"]=>
          string(1) "1"
          ["is_null"]=>
          int(0)
        }
      ["name"]=>
        array(3) {
          ["distinct"]=>
          int(0)
          ["value"]=>
          string(4) "NULL"
          ["is_null"]=>
          int(1)
        }
    }
  }
}

```

###outputToFile($deadColumns)

Takes a list of dead columns, formats them, and outputs them to a .csv file.  The format of **$deadColumns** must be in the same format as the return array from **findDeadColumns()**.  File will be saved either to the default file path as "dead-columns.csv" or what was specified for **$file** Columns in the .csv are as follows:

-Database: The name of the database contanining the dead column
-Table: The name of the table containing the dead column
-Column: The name of the dead column
-Distinct Values: The number of unique values for the dead column
-Value: The distinct value itself
-In the past x months: Records the **$months** value in the file

####Example

```php

<?php
  
  sqlDeadColumnFinder->outputToFile($deadColumns);
  
?>

```
