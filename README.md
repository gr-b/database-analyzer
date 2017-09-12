# Database-analyzer
### Quickly and cleanly document the schema and sample values of a MySQL database

Database-analyzer produces a simple, clean HTML documentation site.  It documents both the structure and the content of
your database, showing column/table metadata along with a representative sample of important values. Best of all,
Database-analyzer can handle massive tables in short amounts of time.


### Prerequisites
1. PHP (version 5.3 or greater)
2. Composer https://getcomposer.org/


# Getting Started
1. Clone the repository:
```
    git clone https://github.com/datto/database-analyzer.git
    cd database-analyzer
```
2. Install dependencies using composer:
```
    composer install
```
3. Fill in database information in `configuration.ini`
4. run `run.php`:
```
    php run.php
```

And you're done!
