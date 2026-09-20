# PandamusRex Email Webhook for WooCommerce

# First, create credential

1. Log into your site's wp-admin using the account you'd like to be responsible for processing the webhook. This account should have the Administrator role.
2. Navigate to /wp-admin/profile.php and scroll down to the Application Passwords section.
3. In the New Application Password Name enter "Email Webhook for WooCommerce" (no quotes) and click on "Add Application Password"
4. Copy the password presented and save it and the username in a safe place like a password manager. You won't be able to retrieve it from wp-admin later.

# Next, add the credential to a new project in your Gmail AppsScript account

1. Log into https://script.google.com/home/ using the account that receives the emails you'd like your site to be notified about.
2. Click on the New project button
3. Click on Untitled Project (at the top of the screen) and give it a meaningful name like "Email Webhook for WooCommerce" (no quotes)
4. Click on the gear (Project Settings)
5. Scroll down to Script Properties
6. Click on Add script property
7. In the Property box type "APIUSER" (no quotes)
8. In the Value box type the username
9. Click on Add script property again
10. In the Property box type "APIPASS" (no quotes)
11. In the Value box paste the application password you obtained above
12. Click on Add script property again
13. In the Property box type "APIURL" (no quotes)
14. In the Value box paste https://YOURDOMAIN/wp-json/pandamusrex/v1/email-webhook/ (be sure to replace YOURDOMAIN with your domain first)
15. OPTIONAL - if you want to limit emails forwarded to the webhook, include a KEYWORD that must be in the title. To do so: Click on Add script property again, in the Property box type "KEYWORD" (no quotes) and in the Value box enter your keyword, e.g. "Zelle" (no quotes). The search is case insensitive.
16. Click on Save script properties

# Next, setup the app permissions

1. At the left, click Project Settings settings.
2. Select the Show "appsscript.json" manifest file in editor checkbox.
3. At the left, click Editor code.
4. At the left, click the appsscript.json file.
5. Replace (or add) the oauthScopes array as shown below. Do not modify anything else.

```
{
  "timeZone": "America/Los_Angeles",
  "dependencies": {
  },
  "exceptionLogging": "STACKDRIVER",
  "runtimeVersion": "V8",
  "oauthScopes": [
    "https://www.googleapis.com/auth/gmail.labels",
    "https://www.googleapis.com/auth/gmail.modify",
    "https://www.googleapis.com/auth/script.external_request"
  ]
}
```

7. At the top, click Save.

# Next, setup the plugin on your site

1. Install and activate this plugin on your site

# Next, upload the webhook script to Google

1. In the AppsScript project, click on the < > (Editor)
2. Replace all the text with the following

```
function myFunction() {
  // Get API USER, PASS and URL from Script Properties
  const scriptProperties = PropertiesService.getScriptProperties();
  
  // Retrieve the value associated with the key 'myApiKey'
  const APIUSER = scriptProperties.getProperty('APIUSER');
  const APIPASS = scriptProperties.getProperty('APIPASS');
  const APIURL = scriptProperties.getProperty('APIURL');
  const KEYWORD = scriptProperties.getProperty('KEYWORD');

  if (APIUSER == null) {
    Logger.log("ERROR: APIUSER not found in Script Properties. Aborting.");
    return;
  } 

  Logger.log("INFO: APIUSER = " + APIUSER);

  if (APIPASS == null) {
    Logger.log("ERROR: APIPASS not found in Script Properties. Aborting.");
    return;
  } 

  if (APIURL == null) {
    Logger.log("ERROR: APIURL not found in Script Properties. Aborting.");
    return;
  }

  Logger.log("INFO: APIURL = " + APIURL);

  if (KEYWORD == null) {
    Logger.log("INFO: KEYWORD not found in Script Properties. Will process each incoming email message.");
  }

  // Create label, if it doesn't yet exist, to mark emails we've processed so we don't do it again
  var labelName = "POSTedToStore";

  var label = GmailApp.getUserLabelByName(labelName);  
  if (label == null) {
    GmailApp.createLabel(labelName);
    Logger.log("INFO: POSTedToStore Label created successfully");
  } 

  // Construct the search query to
  // 1) limit us to most recent month (to avoid processing hundreds of old irrelevant messages)
  // 2) exclude that label
  // 3) require the subjectKeyword
  var searchQuery = "newer_than:1m AND -label:" + labelName;
  if (KEYWORD) {
     searchQuery += " AND subject:" + subjectKeyword;
  }

  Logger.log("INFO: searchQuery = " + searchQuery);

  // Execute the search
  var threads = GmailApp.search(searchQuery);
  if (threads.length > 0) {
    for (var i = 0; i < threads.length; i++) {
      Logger.log("INFO: Processing thread " + (i+1) + " of " + threads.length);
      var messages = threads[i].getMessages();

      // Work with the 0th message in the thread
      if (messages.length > 0) {
        var email_subject = messages[0].getSubject();
        var email_body = messages[0].getPlainBody();
        var email_received = messages[0].getDate();
        var email_sender = messages[0].getFrom();
        Logger.log("INFO: Found message with date: " + email_received);

        const data = {
          email_subject: email_subject,
          email_body: email_body,
          email_received: email_received,
          email_sender: email_sender
        };

        const jsonPayload = JSON.stringify(data);

        var options = {
          method: 'post',
          headers: {
            Authorization: 'Basic ' + Utilities.base64Encode(APIUSER + ":" + APIPASS)
          },
          muteHttpExceptions: true,
          contentType: 'application/json',
          payload: jsonPayload
        }

        var response = UrlFetchApp.fetch(APIURL, options);
        var responseCode = response.getResponseCode();
        var responseBody = response.getContentText();
        if (responseCode === 200) {
          // Label this thread so we don't process it again
          Logger.log("SUCCESS: Store processed webhook successfully");
          label.addToThread(threads[i]);
        } else {
          Logger.log("ERROR: Store failed to process webhook");
          Logger.log("   Response code: " + responseCode);
          Logger.log("   Response body: " + responseBody);
        }
      }
    } // end for threads
  } else {
    Logger.log("INFO: No threads found matching the criteria: " + searchQuery);
  }
}
```

3. Save the file (click on the little disk on near the top of the script editor window)
4. Run the file
5. If you get an Authorization required dialog, click on Review permissions
6. If you get a "Google hasn't verified this app" click on Advanced and then "Go to Email Webhook for WooCommerce (unsafe)"
7. Review the permission request and grant it. The script should run on the most recent month's of emails the first time and then afterwards only run on new emails it hasn't seen before. It uses a label to mark messages it has seen (it does not change their read-unread status.)
8. Go to Triggers (looks like a little alarm clock)
9. Click on Create a new trigger
10. Accept all the defaults (myFunction, Head, Time-driven, Hour timer, Every hour, Notify me daily)
11. There is no need to do Deploy
