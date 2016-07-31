var session = OT.initSession(sessionId),
    publisher = OT.initPublisher('publisher');

session.connect(apiKey, token, function(error) {
  if(error) {
    console.error(error.message);
    return;
  }
  session.publish(publisher);
});

session.on('streamCreated', function(event) {
  session.subscribe(event.stream, 'subscribers', { insertMode : 'append' });
});
