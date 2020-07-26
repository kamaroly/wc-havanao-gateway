# HAVANAO WOOCOMMERCE PAYMENT GATEWAY
This plugin helps you to accept MTN and AIRTEL Payments on your woocommerce store using Havanao.com payment gateway.

## INSTALLATION 
1) Download plugin [here](https://github.com/kamaroly/wc-havanao-gateway/archive/master.zip "Havanao WooCommerce Gateway")
2) Go to wp-admin > Plugins > Upload and activate it.
3) Go to Woocommerce > Settings > Payments and configure it.
4) Provide Havanao API key, that will be used to authenticate on Havanao during payment.

You can set this plugin to testing if you are not in production.
![image](https://user-images.githubusercontent.com/3633772/88485193-bb179280-cf7c-11ea-8ae8-ecbdc30977de.png)


#### CHANGELOG
##### 2020-07-26 
###### 1.0.2
- Added below settings under `Woocommerce > Settings > Payments > Havanao` 
	- Added success payment status configuration
	- Added pending payment status configuration
	- Added Errored payment status configuration
- Added Havanao Payment status call back handler
- Removed Havanao Bill Number label
- Removed Unnecessary Files in plugin.

##### 2020-07-24 
###### 1.0.1
- Fixed MTN bug of `soap:ClientThe requested operation was rejected. Please consult with your administrator.Your support ID is: 13025149561602501851`
