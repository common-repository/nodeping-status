=== NodePing Status ===
Contributors: nosilver4u
Tags: nodeping, uptime, status
Requires at least: 5.0
Tested up to: 5.8
Stable tag: 1.2.1
License: GPLv3

Display NodePing Status page within Wordpress.

== Description ==

Allows you to embed a NodePing status page within WordPress using a simple shortcode. Uses the NodePing API to pull data directly, and allows you to configure how many days of uptime stats to display.

The NodePing status page can be embedded with this shortcode:

	[nodeping_status report="XYZ"]

You can optionally specifiy how many days of uptime to display (days), and how many days to use to calculate total uptime (total):

	[nodeping_status report="XYZ" days="7"]

[NodePing](http://nodeping.com/) is a Server and Website monitoring service. To use this plugin, you need a [nodeping.com](http://nodeping.com) account.

See it in action on my website: https://ewww.io/status/

== Installation ==

1. Upload the 'nodeping-status' plugin to your '/wp-content/plugins/' directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Create a status report in your NodePing account, under Account Settings->Reporting.
1. Get the report ID from the link for the status report.
1. Make sure all the checks in your status report have public reports enabled.
1. Insert shortcode [nodeping_status report="XYZ"] on a page.
1. Done!

== Changelog ==

= 1.2.1 =
* Escape all output.

= 1.2.0 =
* Retrieve stats via a status report without using an API token, which is more secure and much faster.

= 1.1.0 =
* Sort checks by label using natural (human) case-insensitive method

= 1.0.0 =
* Initial version

== Credits ==

Written by [Shane Bishop](https://ewww.io) with special thanks to my [Lord and Savior](https://www.iamsecond.com/).
