# 📘 RC Bazaar

**RC Bazaar** is an online marketplace for buying and selling books, built using **core PHP** and **MySQL**. It features user authentication, admin management, dispute resolution, and payment handling — all organized in an MVC-style folder structure.

## 🚀 Features

- 🧑‍💼 **Admin Panel**: Dashboard, manage users, books, and disputes
- 📚 **Book Listings**: Add, update, search, and display books
- 🛒 **Orders**: Buyers can place and view orders
- 💳 **Payments**: Record and process payment data
- 📝 **Disputes**: Create and manage order disputes
- 👥 **User Authentication**: Register, login, and user profiles
- 🔒 **Role-based Access Control**: Different access for users and admins


## 🛠️ Tech Stack

- **Backend**: PHP (Core)
- **Database**: MySQL
- **Frontend**: HTML, CSS, JavaScript
- **Server**: Apache (XAMPP, WAMP, or LAMP)
- **Version Control**: Git, GitHub


## 📦 Installation

### Clone the Repository

```bash
git clone https://github.com/greysea-cmd/rc_bazaar.git
````

### Set Up the Project

1. Move the folder to your server's root directory:

   * For XAMPP: `htdocs/RC-Bazaar`
   * For WAMP: `www/RC-Bazaar`

2. Import the database:

   * Open **phpMyAdmin**
   * Create a database named `rc_bazaar`
   * Import the `rc_bazaar.sql` file (if available)


3. Configure database credentials in:

```
config/database.php
```

4. Start your server and visit:

```
http://localhost/RC-Bazaar
```

## 📋 To-Do

* [ ] Add input validations and error messages
* [ ] Integrate real payment gateways (e.g., Stripe, Khalti)
* [ ] Add user messaging system
* [ ] Improve responsive UI/UX

## 🤝 Contributing

Contributions are welcome!
Feel free to fork the repository and submit pull requests.

### 🔽 Save Instructions:

1. Create a file named `README.md` in your project root folder.
2. Paste the above content into it.
3. Save and commit:
```bash
git add README.md
git commit -m "Add README file"
git push origin main
````
