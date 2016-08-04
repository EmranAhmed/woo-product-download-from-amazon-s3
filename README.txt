=== Woo Product Download from Amazon S3 ===
Contributors: EmranAhmed
Tags: woocommerce, amazon, aws, s3, download, downloadable product, s3-download
Requires at least: 4.3
Tested up to: 4.5.3
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

WooCommerce Product Download / Upload to / from using Amazon S3 service.

== Description ==
The Woo Product Download from Amazon S3 plugin for WooCommerce enables you to serve digital downloadable products through your Amazon AWS S3 service. Woo Product Download from Amazon S3 is simply allow you to browse existing buckets and files and add your chosen file to file path and can give you access to upload files to specific bucket. When your customer downloads their purchase the extension will serve that file as the download. You can also add non AWS files to your downloadable file path.

= Links =
* [Github](https://github.com/EmranAhmed/woo-product-download-from-amazon-s3/?utm_medium=referral&utm_source=wordpress.org&utm_campaign=Woo+AWS+S3+Readme&utm_content=Repo+Link)

== Installation ==

###Automatic Install From WordPress Dashboard

1. Login to your the admin panel
2. Navigate to Plugins -> Add New
3. Search **Woo Product Download from Amazon S3**
4. Click install and activate respectively.

###Manual Install From WordPress Dashboard

If your server is not connected to the Internet, then you can use this method-

1. Download the plugin by clicking on the red button above. A ZIP file will be downloaded.
2. Login to your site's admin panel and navigate to Plugins -> Add New -> Upload.
3. Click choose file, select the plugin file and click install

###Install Using FTP

If you are unable to use any of the methods due to internet connectivity and file permission issues, then you can use this method-

1. Download the plugin by clicking on the red button above. A ZIP file will be downloaded.
2. Unzip the file.
3. Launch your favorite FTP client. Such as FileZilla, FireFTP, CyberDuck etc. If you are a more advanced user, then you can use SSH too.
4. Upload the folder to wp-content/plugins/
5. Log in to your WordPress dashboard.
6. Navigate to Plugins -> Installed
7. Activate the plugin

== Screenshots ==

1. Amazon Settings Option Panel
2. Downloadable product option
3. Amazon S3 Bucket Menu
4. Browse S3 Bucket
5. List Of Buckets
6. Upload Filed to bucket

== Frequently Asked Questions ==

= How to configure Amazon S3 Account =

- [Create AWS Free Tier Account](https://aws.amazon.com/free/)
- Goto **Amazon S3 Console** and create *buckets* as your need.
- Goto **Security Credentials** and *create Access Keys (Access Key ID and Secret Access Key)*
- *Create New Access Key* and save it on safe place
- Install this plugin and goto **Woocommerce => Settings => Amazon S3 Settings** and save **Amazon S3 Settings** with *Amazon S3 Access Key ID* *Amazon S3 Secret Key* and *Amazon S3 EndPoint*

== Changelog ==

= 1.0.1 =

- Update plugin header file
- Added variable on `ea_wc_amazon_s3_loaded` hook

= 1.0.0 =

- Initial release