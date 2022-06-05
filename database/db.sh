#/bin/bash
FileDir=/www/wwwlogs/db_back
LOGFILE=/www/wwwlogs/db_back/db_.`date +%m%d_%H%M`.sql.gz
/usr/bin/mysqldump -h -u --set-gtid-purged=off -  | gzip > $LOGFILE
#保留最新的五份
ReservedNum=5
date=$(date "+%Y%m%d-%H%M%S")
FileNum=$(ls -l $FileDir|grep ^- |wc -l)

while(( $FileNum > $ReservedNum))
do
    OldFile=$(ls -rt $FileDir| head -1)
    echo  $date "Delete File:"$OldFile
    rm -rf $FileDir/$OldFile
    let "FileNum--"
done
