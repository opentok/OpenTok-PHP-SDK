// Initialize an OpenTok Session object.
var session = OT.initSession(apiKey, sessionId);

// Initialize a Publisher, and place it into the 'publisher' DOM element.
var publisher = OT.initPublisher('publisher');

session.on('streamCreated', function(event) {
  // Called when another client publishes a stream.
  // Subscribe to the stream that caused this event.
  session.subscribe(event.stream, 'subscribers', {
    insertMode: 'append'
  }, function(error) {
    if (error) {
      console.error('Failed to subscribe', error);
    }
  });
});

// Connect to the session using your OpenTok API key and the client's token for the session
session.connect(token, function(error) {
  if (error) {
    console.error('Failed to connect', error);
  } else {
    // Publish a stream, using the Publisher we initialzed earlier.
    // This triggers a streamCreated event on other clients.
    session.publish(publisher, function(error) {
      if (error) {
        console.error('Failed to publish', error);
      }
    });
  }
});
