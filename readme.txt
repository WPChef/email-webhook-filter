=== Email Webhook Filter ===
Contributors: wpchefgadget
Tags: email, webhook, filter, automation, notifications
Requires at least: 5.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==
Email Webhook Filter extends WordPress core e‑mail functionality by forwarding selected outgoing e‑mails to any external system that accepts HTTP webhooks. Define one or more regular‑expression patterns; when a pattern is found in either the message **subject** or **body**, the plugin assembles a JSON payload and posts it to your Webhook URL.  
Optional header‑ or body‑level authentication makes integration with third‑party services secure and flexible.

* **Smart pattern matching** – write simple or complex PCRE patterns, one per line.
* **Header or body authentication** – send a key in a custom HTTP header or as a JSON property.
* **Lightweight & reliable** – hooks into `phpmailer_init` only when needed; no extra queries.

== Installation ==
1. Upload the entire `email-webhook-filter` folder to `/wp-content/plugins/`.
2. Activate **Email Webhook Filter** via *Plugins → Installed Plugins*.
3. Go to *Settings → Email Webhook Filter*, enter:
   * **Webhook URL** – full HTTPS endpoint.
   * **Triggering Patterns** – one PCRE pattern per line (without delimiters).
   * **Authentication Type** – *Header* or *Request Body (JSON)*.
   * **Auth Field Name** – name of the HTTP header or JSON property.
   * **Security Key** – value to be sent.
4. Save changes. That’s it!

== Frequently Asked Questions ==

= Which WordPress e‑mails are intercepted? =  
All messages that pass through WordPress’ default `wp_mail()` / PHPMailer pipeline—including password resets, WooCommerce, contact forms, etc.

= How do I write patterns? =  
Use standard PHP regular expressions *without* forward‑slash delimiters. Examples:

* **invoice** – matches any e‑mail containing the word “invoice”.  
* **^URGENT:** – matches subjects starting with “URGENT:”.  
* **Payment\s+#\d+** – matches “Payment #12345”, etc.

= What does the JSON payload look like? =  
Default structure:
<pre>
{
  "subject": "Order #123",
  "body": "Thank you for your purchase!"
}
</pre>

If authentication type is Header, the key is added as an HTTP header: X-Auth-Key: your-key.
In Request Body (JSON), the key is added as a top‑level property:
<pre>
{
  "subject": "...",
  "body": "...",
  "myAuth": "your-key"
}
</pre>

== Changelog ==

= 1.0.0 =

Initial public release.