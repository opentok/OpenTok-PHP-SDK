# OpenTok Hello SIP PHP

This is a simple demo app that shows how you can use the OpenTok-PHP-SDK to join a SIP call.

## Running the App

First, download the dependencies using [Composer](http://getcomposer.org) in this directory.

```
$ ../../composer.phar install
```

Next, input your own API Key, API Secret, and SIP configuration into the `run-demo` script file:

```
  export API_KEY=0000000
  export API_SECRET=abcdef1234567890abcdef01234567890abcdef
  export SIP_URI=sip:
  export SIP_USERNAME=
  export SIP_PASSWORD=
  export SIP_SECURE=false
  export SIP_FROM=003456@yourcompany.com
```

Finally, start the PHP CLI development server (requires PHP >= 5.4) using the `run-demo` script

```
$ ./run-demo
```

Visit <http://localhost:8080> in your browser.

## Walkthrough

This demo application uses the same frameworks and libraries as the HelloWorld sample. If you have
not already gotten familiar with the code in that project, consider doing so before continuing.

### Main Controller (web/index.php)

This serves `templates/index.php` and passes in a generated session and token. It also provides an
endpoing `/sip/start` which will dial into a SIP endpoint.

### Main Template (templates/index.php)

This file simply sets up the HTML page for the JavaScript application to run, imports the
JavaScript library, and passes the values created by the server into the JavaScript application
inside `web/js/index.js`

### JavaScript Application (web/js/index.js)

The group chat is mostly implemented in this file. It also implements a button to send a POST
request to `/sip/start`, and another button to force disconnect all running SIP calls in the session.

For more details, read the comments in the file or go to the
[JavaScript Client Library](http://tokbox.com/opentok/libraries/client/js/) for a full reference.
