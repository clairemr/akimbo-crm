### For local development
1. Install XAMPP or equivalent
2. Install local version of Wordpress
    - download from https://wordpress.org/download/ and unzip folder into htdocs of XAMPP (or equivalent process)
3. Go to http://localhost/wordpress/ and complete configuration wizard
    - default database connection settings are username = 'root', password = '', host = localhost
    - you will need to create the wordpress database at localhost/phpmyadmin and enter the name in the installation window
4. Run the Wordpress installer
5. Set define( 'WP_DEBUG', true ); in wp-config.php
6. Install woocommerce & Akimbo-CRM, either through the UI or by moving to plugin folder


#### To Do:
- Create installer: install db tables and go through any required options
- How To page/demo site
- Fork repo and work on the installation there, in order to re-install plugin under a different name