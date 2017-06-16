var session = OT.initSession(apiKey, sessionId),
    publisher = OT.initPublisher('publisher');

session.connect(token, function(error) {
  if (error) {
    console.error('Failed to connect', error);
  } else {
    session.publish(publisher, function(error) {
      if (error) {
        console.error('Failed to publish', error);
      }
    });
  }
});

session.on('streamCreated', function(event) {
  session.subscribe(event.stream, 'subscribers', {
    insertMode : 'append'
  }, function(error) {
    if (error) {
      console.error('Failed to subscribe', error);
    }
  });
});
