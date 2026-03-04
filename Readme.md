## PHP API

### Running this project:
```sudo apt install mariadb-server```

1. Create the database
   
```CREATE DATABASE api;```

2. Create the user
   
```CREATE USER 'apiuser'@'localhost' IDENTIFIED BY '123456';```

3. Grant permissions
```GRANT ALL PRIVILEGES ON api.* TO 'apiuser'@'localhost';```

4. Import the data

```mysql -u apiuser -p api < api.sql```

### Make sure you have installed:
php and php-mysql

### In the project's folder, run:
```php -S localhost:8000 api.php```

### Curl examples for each case:
- Login:
  
```curl -X POST http://localhost:8000/login   -H "Content-Type: application/json"   -d '{"email":"user@teste.com","password":"123456"}'```

- Private

```curl -X GET http://localhost:8000/private   -H "Authorization: Bearer TOKEN"```

- Refresh
  
```curl -i -X POST http://localhost:8000/refresh   -H 'Content-Type: application/json'   -d '{"email":"user@teste.com","password":"123456"}'```

- Invalid Private
  
```curl -X GET http://localhost:8000/private   -H "Authorization: Bearer d7ede44308b8aada0bccc175b3947b57"```

### Token details
- By creating a token, the variable 'expiring_time' records the value of the current timestamp + 900 seconds, totalizing the token's lifetime.
- The 'expires_at' variable has the previous data, but formatted into H:m:s, so the user has notion of time.
- A token is revoked when the endpoint refresh is called, updating the revoked_at column by the current timestamp.
- User data is only shown at private, if the revoked_at field is null, and the expires_at field is smaller then the current timestamp.

> OBS:In case you have issues with the sql file, here are the commands to structure the database:

```
CREATE DATABASE api;
USE api;
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE,
    password_hash VARCHAR(255),
    created_at DATETIME
);

CREATE TABLE access_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    token VARCHAR(255),
    expires_at DATETIME,
    revoked_at DATETIME NULL,
    created_at DATETIME,```
    
    FOREIGN KEY (user_id) REFERENCES users(id)
);
CREATE USER 'apiuser'@'localhost' IDENTIFIED BY '123456';
GRANT ALL PRIVILEGES ON api.* TO 'apiuser'@'localhost';
FLUSH PRIVILEGES;
