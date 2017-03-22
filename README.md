# WHMCS Module for OnApp with vCD


>Before installing the WHMCS Module for OnApp with vCloud Director, please ensure that you meet the following requirements:
>
> * WHMCS 6.2+ and 7+ [[Requirements](http://docs.whmcs.com/System_Requirements)] [[Installation](http://docs.whmcs.com/Installing_WHMCS)]
> * OnApp 4.2+ with vCloud Director Integration
> * PHP 5.4+ 

## Installation

1. Define the correct timezone in your PHP settings:

	```date.timezone = {desired timezone}```

	For Example:

	```date.timezone = Europe/London```

2. Download the latest version of the [OnApp PHP Wrapper](https://github.com/OnApp/OnApp-PHP-Wrapper-External/archive/4.2.0.zip)

3. Extract the files so they are located in the following directory: ```{WHMCS root}/includes/wrapper```
	
4. Download the latest version of the [WHMCS Module](https://github.com/OnApp/WHMCS-vCD/archive/master.zip)

5. Extract and upload into the root WHMCS directory.

## Configuration

After installing the WHMCS module, the next step is to make sure that WHMCS is properly configured - that you have configured general system settings, activated payment methods, and set up at least one product group.

### Adding a Server

1. Log in to your WHMCS Admin Area.

2. Go to Setup >Product/Services> Servers.

3. On the page that loads, click Add New Server link.

4. Fill in the form that appears:
	
	![alt text](docs/add_server_1.png)
	
	You only need to fill in the fields mentioned here, with a brief description of them:
	
	> **Name** – the optional OnApp server name.
	
	> **Hostname** – the hostname of the server you're adding. If you connect to the server using secure connection, fill in this field using an https:// prefix.
	
	> **IP Address** – the IP address of the OnApp server. 
	
	> **Enable/disable** – tick the box to disable the server.
	
5.	You can leave the following form blank:

	![alt text](docs/add_server_2.png)
	
6. Specify the server details of your OnApp Installation:

	![alt text](docs/add_server_3.png)
	
	Fill in the following fields:
	
	> **Type** – Select "OnAppvCD" from the dropdown box.
	
	> **Username** – Please use the default 'admin' login for OnApp.

	> **Password** – The password for the specified username.
	
	> **Secure** – tick to use SSL for connections.
	
7. Click "Save Changes" to add the server to WHMCS.

### Creating a Server Group

1. Log in to your WHMCS Admin Area.

2. Go to Setup > Product/Services > Servers.

3. On the page that loads, click Create New Group link.

4. Fill in the form that appears:

	![alt text](docs/add_group_1.png)
  > **Name** - Give a name to a server group.
  
  > **Selected Servers** - Specify the server that you created previously to add to the server group.

5. Click "Save Changes" to add the new server group to WHMCS.

### Creating a Product Group

1. Go to Setup > Products/Services > Products/Services in your WHMCS Control Panel.

2. Click the "Create a New Group" link.

3. Fill in the form that appears.

	![alt text](docs/add_group_2.png)
	
4. Click Create Group button.

### Creating a Product

When you create a product based on the OnApp vCloud Director WHMCS Module, you connect WHMCS to your OnApp Server and specify the type of service you want to offer. With our current module, you can choose one of the following provisioning models to choose from:

* **Single Organization** - This is where you select a single organization and during sign up, WHMCS deploys a low-level (eg, vApp Author) user where they have access to shared resources within a pre-created virtual data center.
* **Multiple Organization** - This is where during sign up, WHMCS will deploy a new Organization and a new Organization Administrator user where the end user can deploy new virtual data centers from your pre-created vCloud Orchestration models.

Each product must be assigned to a group which can either be visible or hidden from the order page (products may also be hidden individually).

1. Go to Setup > Products/Services > Products/Services in your WHMCS Control Panel.

2. Click the "Create a New Product" link and fill in the form that appears.

	![alt text](docs/add_product_1.png)
	
	> **Product Type** - Choose the type of Product, for this module we recommend selecting "Other".
	
	> **Product Group** - Select the name of the Product Group that you had created previously.
	
	> **Product Name** - Specify the desired product name.
	
3. Click "Continue".

4. Complete the forms in the "Details" and "Pricing" tabs. For instructions, refer to the [WHMCS documentation](http://docs.whmcs.com/).

5. Go to the "Module Settings" tab and select "OnAppvCD" from the drop-down menu. 

6. Select the Server Group that you had created previously and click "Save Changes".

7. Select the appropiate OnApp server that you had created previously from the "Server" drop-down menu.

8. Complete the following form with the details of your product.

	![alt text](docs/add_product_2.png)
	
	> **Hypervisor** - Select the name of the Compute Resource that links to your vCloud Director instance within OnApp.
	
	> **Timezone** - This will be the timezone that will be selected for all newly created users.
	
	> **Locale** - This will be the locale that will be selected for all newly created users.
	
	> **Roles** - Depending on the "Organization Type" you will select, this should be "vCloud vApp Author" for the "Single Organization" model and "vCloud Organization Administrator" for the "Multiple Organization" model.
	
	> **Billing Plan** - This will be the billing plan assigned to all newly created users. If you are using the "Single Organization" model, then you should ensure that the Organization you select in the "Groups" drop-down has access to this billing plan.
	
	> **Group Billing Plans (Only shown for Multiple Organization model)** - Select the billing plans that the created organization will have access to.
	
	> **Groups (Only shown for Single Organization model)** - Select the Organization that all newly created users will be provisioned in.
	
	> **Organization Type** - Select whether you want to use the Single or Multiple Organization model.
	
	> **Billing Type** - Whether you want to use a post-paid or pre-paid model of billing.
	
9. Save Changes.

Now that the product has been created, you will be able to see it on the order form and setup customers using WHMCS.

## Setting up cronjobs

Add the following command to your cronjobs:

```bash
# run invoice generator at 01:00 on the first day of each month
0 0 1 * *    /usr/bin/php -q {WHMCS}/modules/servers/onappusers/cronjobs/generate-invoices.php
```

*{WHMCS} is the full path to your WHMCS directory.*

**_DO NOT SETUP INVOICE GENERATOR BEFORE TESTING (SEE BELOW)!_**


### Advanced usage
#####_Invoice generator_
By default it generates customers invoices relying on collected statistics for the previous month.
As usual it should be run once a month when you want generate invoices.

You can force generator to generate invoices for the certain period by passing desired starting and ending dates in format 'YYYY-MM-DD HH:MM' as a parameters:

```bash
/usr/bin/php -q {WHMCS}/modules/servers/OnAppvCD/cronjobs/generate-invoices.php --since='2017-01-01 00:00' --till='2017-01-31 23:00'
```

**_for the proper calculation all dates should be entered in the server's timezone_**

### Prepay usage
You can enable hourly billing in product settings, and generate invoices by next command:
```bash
/usr/bin/php -q {WHMCS}/modules/servers/OnAppvCD/cronjobs/hourly.php -l
```

### Testing invoice generator
We strongly recommend to test invoice generator before using in production to be sure that it works properly.
For testing you should setup statistics collector (or run it from console) and run tester:

```bash
# test invoices for previous month
/usr/bin/php -q {WHMCS}/modules/servers/OnAppvCD/cronjobs/test-invoices.php -l

# test invoices for previous month for WHMCS user id = XXXX
/usr/bin/php -q {WHMCS}/modules/servers/OnAppvCD/cronjobs/test-invoices.php -l -whmcsuserid=XXXX

# test invoices for current month
/usr/bin/php -q {WHMCS}/modules/servers/OnAppvCD/cronjobs/test-invoices.php -l --since='2017-01-10 00:00'

```

_Tester functionally is the same as generator itself, but it writes processed data to the file for review instead of generating real invoices._


