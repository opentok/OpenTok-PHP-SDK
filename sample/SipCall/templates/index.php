<!DOCTYPE html>
<html>
  <head>
    <title>Wormhole Sample App</title>
    <link rel="stylesheet" href="//fonts.googleapis.com/css?family=Muli:300,300italic" type='text/css'>
    <link rel='stylesheet' href='/stylesheets/style.css' />
    <link rel='stylesheet' href='/stylesheets/pattern.css' />
    <script src="https://code.jquery.com/jquery-3.1.0.min.js"></script>
    <script src="https://static.opentok.com/v2/js/opentok.min.js"></script>
    <script>
      var sessionId = "<?php echo $sessionId ?>";
      var token = "<?php echo $token ?>";
      var apiKey = "<?php echo $apiKey ?>";
    </script>
  </head>
  <body>
    <div class="main-header">
      <header>
        <h1>SIP Interconnect</h1>
        <h3>Test OpenTok's SIP Interconnect API</h3>
      </header>

      <div id="selfPublisherContainer">
        <h3>Your Publisher</h3>
      </div>

      <section class="panel panel-default" id="sip-controls">
         <a href="#" class="btn tb btn-positive" id="startSip">Start SIP Call</a>
          <a href="#" class="btn tb btn-negative" id="stopSip">End SIP Calls</a>
      </section>
    </div>

    <div class="streams">
      <h3>WebRTC Streams</h3>
      <div class="main-container" id="webrtcPublisherContainer">
      </div>
      <h3>SIP Streams</h3>
      <div class="main-container" id="sipPublisherContainer"></div>
    </div>


  </body>
  <script src="js/index.js"></script>
</html>
