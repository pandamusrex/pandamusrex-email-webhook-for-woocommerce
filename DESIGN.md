# PandamusRex Email Webhook for WooCommerce

## TO DO

- [x] Design tables
- [x] Comment out old post type code
- [x] Create tables
- [x] Create persistence layer for each table
- [x] Change Google Script to post as JSON
- [x] Allow no-auth POST endpoint writing of email to table
- [x] Create email list view like memberships
- [x] Create single email view (read only)
- [x] Add history list to single email view
- [ ] Add order picking to single email view
- [ ] Add delete to single view; delete from list view
- [ ] Clean up naming throughout to Email Receipts or Payments By Email or something
- [ ] Add auth checking to POST endpoint
- [ ] Delete old post type code
- [ ] Add large meta box to order to show associated emails - with links to single email view
- [ ] Write setup guide

- [ ] Add default view to list view to show emails needing disposition
- [ ] Add load more to bottom of list view to load another 50 using jquery dom insertion

## Google Scripts

- Upload this as a new script to https://script.google.com/home/
- Run it manually to make sure it works - I think that's when you'll be asked for permission to connect it to your inbox
- Verison it and deploy it https://developers.google.com/apps-script/concepts/deployments

```
function myFunction() {
  var url = "https://pandamusrex.com/wp-json/pandamusrex/v1/email-webhook/";
  // TODO AUTHORIZATION API KEY

  var labelName = "POSTedToStore";
  var subjectKeyword = "Zelle";

  // If label doesn't exist, create the label and exit
  var label = GmailApp.getUserLabelByName(labelName);  
  if (label == null) {
    GmailApp.createLabel(labelName);
    Logger.log("INFO: Label created successfully");
    return;
  } 

  // Construct the search query
  var searchQuery = "-label:" + labelName + " AND subject:" + subjectKeyword;

  // Execute the search
  var threads = GmailApp.search(searchQuery);
  if (threads.length > 0) {
    for (var i = 0; i < threads.length; i++) {
      Logger.log("INFO: Processing thread " + (i+1) + " of " + threads.length);
      var messages = threads[i].getMessages();

      // Work with the 0th message in the thread
      if (messages.length > 0) {
        var subject = messages[0].getSubject();
        var plainBody = messages[0].getPlainBody();
        var messageDate = messages[0].getDate();
        Logger.log("INFO: Found message with date: " + messageDate);

        var options = {
          "method": "post",
          "headers": {
//          "Authorization": "Basic " + Utilities.base64Encode(" ...account.SID... : ...auth.token... ")
          },
          "muteHttpExceptions": true,
          "payload": {
            "date": messageDate,
            "subject": subject,
            "plainBody":  plainBody,
          }
        }

        var response = UrlFetchApp.fetch(url, options);
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

## Database schema

- Each forwarded mail needs to be stored and assocated (or not) with an order on the system

```
ID
datetime email received at inbox
email subject
sender email
email body
order ID...
  -1 indicates parked aka marked as not assigned to an order but i don't want to delete it
  0 indicates awaiting order assignment / needs human intervention
  >0 indicates order ID this was assigned to
```

and a history table too

```
ID
email ID
datetime history entry
text of history entry (e.g. webhook received, order assigned)
user id...
  0 indicates the "system" did the action
  >0 indicates the userid of the user that did the action
```