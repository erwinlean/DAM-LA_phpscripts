# File to copy crontab into the server

# New file to test > copy_to_etrade.php
0 1 * * * php /opt/assets/copy_to_etrade.php

# Every other crontab is functional
# Copy named on the server in this scrip: copy_to_etiquetado.php
0 3 * * * php /opt/assets/copy.php
# Test named on the server in this scrip: principal_script.php
0 4 * * * php /opt/assets/test.php
0 5 * * * /home/adminlocal/.local/bin/aws s3 sync s3://laanonima-dam s3://laanonima-dam-backup
0 7 * * * rm -rf /tmp/*.gif /tmp/*.jpg /tmp/*.JPG /tmp/*.png /tmp/*.psd