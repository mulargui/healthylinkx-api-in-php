sudo docker rm phpapi
#sudo docker run -ti -p 8081:80 -v /vagrant/apps/healthylinkx-api-in-php:/var/www/html --name phpapi php /bin/bash
sudo docker run -ti -p 8081:80 -v /vagrant/apps/healthylinkx-api-in-php:/var/www/html --name phpapi --link MySQLDB:MySQLDB php /bin/bash