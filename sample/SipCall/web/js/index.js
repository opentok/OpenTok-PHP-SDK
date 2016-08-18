var session = OT.initSession(sessionId);
session.on("streamCreated", function (event) {
  var tokenData = event.stream.connection.data;
  if (tokenData && tokenData.includes("sip=true")) {
    var element = "sipPublisherContainer";
  } else {
    var element = "webrtcPublisherContainer";
  }
  session.subscribe(event.stream, element, { insertMode: "append" });
})
.connect(apiKey, token, function (err) {
  if (err) return;
  session.publish("selfPublisherContainer", {
    insertMode: "append",
    height: "120px",
    width: "160px"
  });
});
$('#startSip').click(function (event) {
  $.post('/sip/start', {sessionId: sessionId, apiKey: apiKey})
  .fail(function () {
    console.log('Failed to start SIP call - sample app server returned error.');
  });
});
$('#stopSip').click(function (event) {
  OT.subscribers.where().forEach(function (subscriber) {
    var connection = subscriber.stream.connection;
    if (connection.data && connection.data.includes("sip=true")) {
      session.forceDisconnect(connection.connectionId);
    }
  });
});
