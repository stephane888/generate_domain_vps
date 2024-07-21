# Drupal module : generate_domain_vps

Permet de generer de creer un hote virtuel pour des configurations dynamiques.

## installation

```
composer require habeuk/generate_domain_vps
```

### Configuration 
Vous devez modifier votre fichier /etc/sudoers afin d'ajouter les authorisations necessaires.

```
%www-data ALL=(ALL) NOPASSWD:/usr/bin/lego, NOPASSWD:/usr/bin/rm /etc/apache2/sites-available/*, NOPASSWD:/usr/bin/rm /home/wb-horizon/vhosts/*, NOPASSWD:/usr/bin/a2dissite, NOPASSWD:/usr/bin/a2ensite, NOPASSWD:/usr/bin/tee, NOPASSWD:/usr/bin/echo, NOPASSWD:/usr/bin/systemctl reload apache2
```

### Author

<div>
<img alt="Logo habeuk" src="https://habeuk.com/sites/default/files/styles/medium/public/2023-08/logo-habeuk.png" height="40px">
<strong> Provide by <a href="https://habeuk.com/" target="_blank"> habeuk.com </a> </strong>
</div>