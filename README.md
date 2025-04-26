
# Telegram Wallet Transaction Notifier - @txnNotifierBot
Get notified on Telegram when your wallet address got a new incoming and outgoing transaction.

Telegram: 
[![telegram](https://img.shields.io/badge/Telegram-@txnNotifierBot-blue)](https://t.me/txnNotifierBot)


## Features
- Adding address including legacy, segwit and taproot address
- Add/edit label to the address for faster identification
- Remove wallet address
- Check valid and invalid address
- Redirects to chosen block explorer's transaction page after clicking the "Check Transaction" and "Check address"
- Check current balance and transaction amount in BTC/crypto and USD conversion
- Choose block explorer to view address and txid


## Command:
- /start - to start the bot, can be seen on the first visit the bot.
- /menu - shows all the available features of the bot

## Installation
- Add Controller Wallet_address_notifier_bot.php
- Add Model Wallet_notifier_bot_model.php
- Add Library Telegram_api.php
- run cron job Wallet_address_notifier_bot > checkNewTransaction() every minute, depends on you

#### Cron Command
 \* * * * * /folder index.php Wallet_address_notifier_bot checkNewTransaction >> /var/log/wallet_notification.log 2>&1


## Donations
- BTC: bc1q00pxz0k04ndxqdvmkr8kj3fwtlntfctlzp37xl
- ETH: 0x6e212cB02e53c7d53b84277ecC7A923601422a46
- USDT (Trc20): TWyvoyijQY2mhnpUMY4bmpk3fX8A66KZTX
- XMR - 45eoPvxBkZeJ2nSQHGd9VRCeSvdmKcaV35tbjmprKa13UWVgFzArNR1PWNrZ9W4XwME3iJB9gzMKuSqGc2EWR4ZCTX66NAV 

