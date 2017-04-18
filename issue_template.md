<!--
Thanks for reporting issues back to Bookmarks! This is the issue tracker of Bookmarks, if you have any support question please check out https://nextcloud.com/support

To make it possible for us to help you please fill out below information carefully.
--> 
### Steps to reproduce
1.
2.
3.

### Expected behaviour
Tell us what should happen

### Actual behaviour
Tell us what happens instead

### Server configuration
<!--
You can use the Issue Template application to prefill most of the required information: https://apps.nextcloud.com/apps/issuetemplate
-->

**Operating system**:

**Web server:**

**Database:**

**PHP version:**

**Nextcloud version:** (see Nextcloud admin page)

**Contacts version:** (see Nextcloud apps page)

**Updated from an older Nextcloud or fresh install:**

**Signing status:**

```
Login as admin user into your Nextcloud and access 
http://example.com/index.php/settings/integrity/failed 
paste the results here.
```

**List of activated apps:**

```
If you have access to your command line run e.g.:
sudo -u www-data php occ app:list
from within your Nextcloud installation folder
```

**Nextcloud configuration:**

```
If you have access to your command line run e.g.:
sudo -u www-data php occ config:list system
from within your instance's installation folder

or

Insert your config.php content here
Make sure to remove all sensitive content such as passwords. (e.g. database password, passwordsalt, secret, smtp password, …)

**Are you using external storage, if yes which one:** local/smb/sftp/...

**Are you using encryption:** yes/no

**Are you using an external user-backend, if yes which one:** LDAP/ActiveDirectory/Webdav/...

#### LDAP configuration (delete this part if not used)

```
With access to your command line run e.g.:
sudo -u www-data php occ ldap:show-config
from within your Nextcloud installation folder

Without access to your command line download the data/owncloud.db to your local
computer or access your SQL server remotely and run the select query:
SELECT * FROM `oc_appconfig` WHERE `appid` = 'user_ldap';


Eventually replace sensitive data as the name/IP-address of your LDAP server or groups.
```

### Client configuration
**Browser:**

**Operating system:**

**CardDAV-clients:**

### Logs
#### Web server error log
```
Insert your webserver log here
```

#### Nextcloud log (data/nextcloud.log)
```
Insert your Nextcloud log here
```

#### Browser log
```
Insert your browser log here, this could for example include:

a) The javascript console log
b) The network log 
c) ...
```
