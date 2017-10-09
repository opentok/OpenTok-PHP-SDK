:: Why? because windows can't do an OR within the conditional
IF NOT DEFINED API_KEY GOTO defkeysecret
IF NOT DEFINED API_SECRET GOTO defkeysecret
GOTO skipdef

:defkeysecret

:: OpenTok Project Configuration (find these at https://tokbox.com/account)
SET API_KEY=
SET API_SECRET=

:: SIP Destination Configuration (find these with your SIP server provider)
SET SIP_URI=
SET SIP_USERNAME=
SET SIP_PASSWORD=
SET SIP_SECURE=false

:: SIP from (optional)
SET SIP_FROM=

:skipdef

RD /q /s cache

php.exe -S localhost:8080 -t web web/index.php
