#Purpose = ssh or sftp into ec2 instance
#Created on 1-NOV-2014
#Author = John Meah
#Version 1.0

echo -n "Do you want to point to local after push (y\n) > "
read local
echo "You entered $local\n"


echo -n "Enter the details of your deployment (i.e. 4-FEB-2014 Updating this script.) > "
read comment
echo "You entered $comment\n"
#set -v verbose #echo on

#copy fe settings to push to git...
cp ./module/Application/src/Application/Model/MemreasConstants.bewQ.php ./module/Application/src/Application/Model/MemreasConstants.php

#Push to AWS
echo "Committing to git..."
git add .
git commit -m "$comment"
echo "Pushing to github..."
set -v verbose #echo on
git push

if [ "$local" = "y" ]
then
	cp module/Application/src/Application/Model/MemreasConstants.localhost.php module/Application/src/Application/Model/MemreasConstants.php
fi

#eb events -f

#curl https://memreasdev.memreas.com:9002/index?action=clearlog
curl https://memreasdev.memreas.com:9002/index?action=gitpull
