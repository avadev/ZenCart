ZenCart-1.51
============

INTRODUCTION
============
The Zen Cart Connector for AvaTax is a Zen Cart compliant module that
integrates the Zen Cart check-out process with the cloud computing 
sales tax service AvaTax, provided by Avalara, Inc. for the calculation,
compliance and reporting of sales tax. 
 
AvaTax reduces the audit risk to a company by providing a cloud-based
sales tax service that makes it simple to do rate calculations, while managing
exemption certificates, filing forms and remitting payments.

The module supports two modes; i) Basic ii) Pro

The AvaTax Basic service provides limited use of the AvaTax sales tax codes, in
particular P0000000 for Personal Taxable goods, a Shipping tax code and NT for
Not Taxable goods.

The AvaTax Pro service provides full access to the AvaTax sales tax codes.
The Pro service is required for states like New York were sales tax rates
are  based on the type of product, the delivery address, and the time of year.

Sales Tax is calculated based on the the sales tax codes assigned to the line
items included in the order, the delivery address, and the applicable sales tax
legislation in that state.

Access to a fully functional development account can be requested by contacting
Avalara, Inc.

AvaTax Development service: https://admin-development.avalara.net
AvaTax Production service: https://admin-avatax.avalara.net  

Version 1.15.R of the module is compatible with Zen Cart 1.5.1 

REQUIREMENTS
============
a) The service uses the AvaTax REST api for processing transactions.
b) The server needs to support cURL


INSTALLATION
============
Installing the module is done as for any custom Zen Cart module, in particular
to copy the files and folders into the appropriate directories.

The module does NOT require any existing program files to be modified!

Please take care - there are folders and files that have duplicate names
this is not an error - it is to comply with Zen cart module standards.
The files are different and must be copied to their correct places.

There are no overrides - you can copy files and folders individually or upload 
the complete includes folder.

a) Copy the FILE "ot_avatax.php"
from zip file zen_cart_1_15_R\includes\languages\english\modules\order_total\
to folder www.yoursite/includes/languages/english/modules/order_total/

b) Copy the FOLDER "avatax" (contains 1 program files)
from zip file zen_cart_1_15_R\includes\modules\
to folder www.yoursite/includes/modules/

c) Copy the FILE "ot_avatax.php"
from zip file zen_cart_1_15_R\includes\modules\order_total\
to folder www.yoursite/includes/modules/order_total/


CONFIGURATION
=============
a) To display sales tax line item if sales tax amount = zero
Admin - Select Configuration -> My Store -> Sales Tax Display Status
Select "1" - to display & "0" to not display

b) We suggest you disable the module ot_tax - or make sure that the Zen Cart
sales tax module will not conflict with AvaTax!  

c) Using the Admin Page - Select Modules -> Order Total 
The AvaTax module will be showing alphabetically (probably top) in the
list of Order Total Modules available for configuration.

Select -> Install

Complete the information requested following the instructions in the form

AvaTax Version
--------------
Select Basic or Pro according to the terms of your AvaTax Account. You may
select the Pro version of the module with the AvaTax Basic service to customize
your use of sales tax codes.

Please contact Avalara, Inc to discuss the difference between the Basic and
Pro versions of the AvaTax service.

NB: If selecting Pro version - you will be required to extend the module
to read a sales tax code from your product data structure, after extending
it to include a sales tax field.

AvaTax Mode
-----------
Only select Production if you have completed the GO LIVE process with Avalara
and have a valid production account and license key.

AvaTax Company Code
-------------------
For Trial version enter the company code provided. For a full Development
account enter the company code for the company provided to you. For a
Production account enter the company code set up during Go Live training. 
Please make sure that you have configured Nexus correctly.

AvaTax Selected States
----------------------
The module allows limiting  sales tax calculations by AvaTax to explicitly
listed states. The default administrative option is for this field to be empty.
This will result in all orders being sent to the AvaTax service on checkout. 

It is important that you consult with an accountant or sales tax advisor on 
your legal obligations to collect Sales Tax - known as Sales Tax Nexus.

AvaTax Account Number
---------------------
Enter the AvaTax Account number provided.

AvaTax License Key
------------------
Enter the AvaTax License Key for your account

Select Destination Address to use for Sales Tax
-----------------------------------------------
Select Shipping unless your site ONLY sells digital goods. Sales Tax regulations
require that the taxable address for digital goods is the customers billing
address. The module does requires customization to support physical and
digital goods - select Shipping address and contact adTumbler.

Shipping Tax Code
--------------------
FR020100 is the AvaTax sales tax code when shipping by public carrier (USPS,
Fedex, etc.)- without change to their rates - please refer to Avalara for the
correct sales tax codes codes if you do not use public carriers or bill
additional charges for shipping. The module only caters for one category of
shipping. 

Sales Tax Description
---------------------
The sales tax description to be shown to the user. 

Show location code
------------------
If selected the sales tax description will include the delivery city entered
by the user in the shipping/billing address field at checkout.  

Primary Address
---------------
Sales tax law requires that a sales tax transaction record the place from which
good are shipped. This version of the module does not support Drop Shipments.
However, the module will calculate sales tax correctly provided that a valid
head office address is entered. 


OPERATION OF MODULE
==================
a) There is no test connectivity to AvaTax function implemented at this time. 
The best way to test connectivity is to place a test order, with a delivery
address in the Nexus state configured, and to inspect the cart for a sales
tax amount after completing the shipping page.

b) Sales Tax will be calculated and displayed on completion of delivery details
using the order check out form.

c) The sales tax transaction will be added to the AvaTax cloud service when the
user first creates order. The source code is commented on how to delay the
creation of a sales tax transaction in AvaTax to when the order is completed.


SALES ORDER PROCESSING
======================
The osCommerce Connector for AvaTax is provided with sample code and the
following instructions to Commit & Void transactions in AvaTax by using the
osCommerce administration page to change the status of orders.

It is strongly suggested that the financial controller, accountant, or legal
consultant is engaged to make sure that the sales tax integration is
technically correct, and compliant!

Step 1
------
Copy the FOLDER "avatax" (contains 1 program file)
from zip file oscommerce_2_3_R\admin\includes\modules\
to folder www.yoursite/admin/includes/modules/avatax/erp.avatax.php

Note: if you have renamed the admin folder copy to
www.yoursite/admin(your admin)/includes/modules/avatax/erp.avatax.php

Step 2
------
Select option - Internationalization -> Orders Status
http://yoursite.com/admin/orders_status.php

Review and add status as needed:

Status "Delivered" usually exists - note id on url (typically #3)
New Status "Cancelled" - note id on url (typically #5)

Step 3
------
Update the following one osCommerce files

File: orders.php - NB - instance in yoursite/admin/orders.php

Insert the following 9 lines of code after: 
if ($status < 1) break;

  require_once DIR_WS_MODULES . 'avatax/erp.avatax.php';
  $avatax_canceled = 5;
  $avatax_delivered = 3;
  if ($status == $avatax_canceled) { 
    zc_avatax_cancel_transaction($oID);
  }
  if ($status == $avatax_delivered) { 
    zc_avatax_commit_transaction($oID);
  } 

Insert the following 2 lines of code after: 
zen_remove_order($oID, $_POST['restock']);

  require_once DIR_WS_MODULES . 'avatax/erp.avatax.php';
  zc_avatax_cancel_transaction($oID);

Operations
----------
i) Use the osCommerce administration to change status to "Delivered"
AvaTax will "Commit" transaction if order exists, and is a valid document status

ii) Use the osCommerce administration to change status to "Cancelled"
AvaTax will "Void" transaction if order exists in AvaTax

iii) Use the osCommerce administration to delete an order
AvaTax will "Void" transaction if order exists in AvaTax


PRODUCTION ACCOUNT - GO LIVE PROCESS
====================================
Using the Zen Cart Administration page:
Select Modules -> Order Total -> AvaTax:

a) Replace the Development Company Code with the company code created for
you production company during Go Live training.

b) Select 'Production' in the field: Account Type

c) Update the AvaTax Account number

d) Update the AvaTax License Key 

Save the form.

NB: Do check with the sales tax administrator that any custom tax codes,
and Nexus configurations, created in the development account have been
configured for your production company.

It is suggested that customers set up two companies in their production
AvaTax account - a test company for test transactions - and a production
company for normal operations.


ADDRESS VALIDATION
==================
We have removed the administrative option to block an order if the address
entered by a user does not provide a valid sales tax calculation.

It is the responsibility of the site developer to handle the AvaTax error
generated if the address fields are populated with invalid data by the user.

The following address will generate an error:

Street: Nowhere Street
City: Nowhere City
Zip: 99999

AvaTax error: JurisdictionNotFoundError:
Unable to determine the taxing jurisdictions.
