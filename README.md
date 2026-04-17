# WooPoints – WooCommerce Points & Rewards

A premium WooCommerce loyalty points system. Customers earn points equal to the amount spent on every purchase. Includes an admin dashboard with user management and a grand prize raffle wheel where user slice sizes are proportional to their points.

## ✨ Features

### Customer Loyalty Points
- **Automatic Points Earning** – Customers earn points equal to their order total on every completed purchase
- **Points Display** – Points balance shown on cart, checkout, and order confirmation pages
- **User Dashboard** – Customers can view their points balance and earning history via a WooCommerce My Account tab

### Admin Dashboard
- **Overview Stats** – Total users, total points in system at a glance
- **Customer Points Table** – View all customers with their current balance, total earned, and total spent
- **Search & Pagination** – Quickly find any customer by name or email
- **Manual Points** – Add points to any user manually (dropdown selector + amount input)
- **Edit Points** – Modify any customer's point balance via modal
- **Delete Customer** – Remove individual customer point records
- **Delete All Points** – Bulk delete all customer point data

### 🎡 Grand Prize Raffle Wheel
- **Visual Spinning Wheel** – Beautiful Canvas-based pie chart wheel with proportional slices based on user points
- **Server-Side Integrity** – Winner is securely determined on the backend via weighted random selection (more points = higher chance)
- **Prize Configuration** – Set a custom prize name
- **Spin History** – Full log of all raffle spins with winner details and timestamps
- **Clear History** – One-click button to clear all raffle history

### 🎨 Design
- **Dark Theme** – Sleek black & gray color scheme
- **Responsive** – Works on all screen sizes
- **Animated** – Smooth spinning wheel animation with easing
- **Toast Notifications** – Non-intrusive success/error feedback

## 📋 Requirements

- WordPress 5.0+
- WooCommerce 5.0+
- PHP 7.4+

## 🚀 Installation

1. Download or clone this repository
2. Upload the `woo-points-rewards` folder to `/wp-content/plugins/`
3. Activate the plugin via **Plugins → Activate** in WordPress admin
4. Navigate to **WooPoints** in the admin sidebar

## 📁 File Structure

```
woo-points-rewards-github/
├── woo-points-rewards.php          # Main plugin file
├── includes/
│   ├── class-wpr-admin-dashboard.php   # Admin dashboard & raffle page
│   ├── class-wpr-ajax-handler.php      # AJAX endpoints (spin, save prize)
│   ├── class-wpr-database.php          # Database table creation
│   ├── class-wpr-order-handler.php     # WooCommerce order hooks & frontend banners
│   ├── class-wpr-points-manager.php    # Core points logic (add, deduct, query, delete)
│   └── class-wpr-user-dashboard.php    # Customer-facing My Account tab
├── assets/
│   ├── css/
│   │   ├── admin-dashboard.css         # Admin styles (dark theme)
│   │   └── frontend-dashboard.css      # Frontend styles
│   └── js/
│       └── admin-dashboard.js          # Raffle wheel, modals, AJAX handlers
├── README.md                           # This file
└── SQA_REPORT.md                       # Formal Quality Assurance Testing Report
```

## 🔧 How It Works

### Points System
1. Customer places an order
2. When order status changes to "completed", points equal to the order total are automatically credited
3. Points are stored in a custom database table (`wp_wpr_points`)
4. Transaction history is logged in `wp_wpr_points_log`

### Grand Raffle
1. Admin sets a prize name in the raffle page
2. The spinning wheel displays all users with points as proportional slices
3. Admin clicks "Spin the Wheel" to randomly select a winner
4. Winner is securely determined on the server by weighted random selection (more points = higher chance)
5. Wheel visually animates to the matched winner
6. Spin result is logged in `wp_wpr_spin_history`

## 📝 License

MIT License

## 👤 Author

**Joytirmoy Halder Joyti**
