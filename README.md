# Firebird.class.php
This is a very lightweight wrapper for the old ibase\_\* functions. It provides
an object oriented API to communicate with Firebird and converts most ibase
warnings and false return values into exceptions.

There is currently no documentation but it is easy to use. Most functions have
been converted from ibase_xxx_yyy($resource) to Resource::xxxYyy. The
FirebirdStatement is one of the few exceptions to this rule, ibase_execute
expects varargs to be passed to it but FirebirdStatement uses the more familiar
bindParam and bindValue methods that can be found in other database wrappers. To
use the old varargs form you should call the executeVarArgs method.

## Example
Opening a connection:

```PHP
$db = new Firebird($database, $user, $password);
```

Executing a prepared statement:

```PHP
$stmt = $db->prepare('INSERT INTO TEST (DATA) VALUES (?)');
$stmt->bindValue(1, 'Test data');
$stmt->execute();
```

Since it is very similar to the
[SQLite3](https://secure.php.net/manual/en/book.sqlite3.php)
and [PDO](https://secure.php.net/manual/en/book.pdo.php) classes it shouldn't be
too hard to understand.

## TODO
 * Documentation (to understand the classes and methods in Firebird.class.php
 you can read the [ibase](https://secure.php.net/manual/en/book.ibase.php)
 documentation but it is not fully documented, I'll have to figure out what some
 of their functions do in order to write my own documentation)
 * Use exceptions in the FirebirdService class, currently it just calls the
 ibase functions without hiding their warnings and returns their error values
 * Test the whole class, most frequently used methods such as query, prepare,
 and execute have been tested but some things such as dropDb and most of the
 FirebirdService class haven't been tested
