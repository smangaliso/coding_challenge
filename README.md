<h1>Setup Guide</h1>
<p>Follow the steps below to clone the project, configure your environment, and run the migrations.</p>
<h2>Step 1: Clone the Repository</h2>


```
git clone https://github.com/smangaliso/coding_challenge.git 
```

navigate into the project direectory
```
cd coding_challenge
```

<h2>Step 2: Create a MySQL Database</h2>

1. **Log in to your MySQL server.**

```
mysql -u root -p
```

2. **Create a new database for your application**

```
CREATE DATABASE coding_challenge;
```

3. **Exit MySQL**

```
exit;
```

<h2>Step 3: Configure the .env File</h2>

1. **Copy the example environment file:**

- Manually copy the file named `.env.example` and rename it to `.env` in your project directory.

2. **Open the .env file and configure the database settings:**

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=coding_challenge
DB_USERNAME=root
DB_PASSWORD=your_password
```
Make sure to replace `root`, and `your_password` with the appropriate values.

<h2>Step 4: Run Migrations</h2>

```
php artisan migrate
```
