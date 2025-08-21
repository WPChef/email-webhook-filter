Create an inbox on WebhookInbox
Go to https://webhookinbox.com/.

Click Create Inbox.

Copy the Inbox URL (looks like):

https://api.webhookinbox.com/i/<INBOX_ID>/in/

Configure Email Webhook Filter
Open Settings → Email Webhook Filter and set:

Webhook URL

https://api.webhookinbox.com/i/<INBOX_ID>/in/
Triggering Patterns (one per line)
Use the exact pattern from your screenshot:

/Your MFA verification code is: \d{6}/
(This triggers when the email body contains “Your MFA verification code is: 123456”.)

Authentication Type: Request Body (JSON)

