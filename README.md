# Twenty Twenty Four Spenpo Child Theme

This is a child theme for the Twenty Twenty Four theme. It is used to add custom functionality to the website.

## Development

To develop on this theme, you can clone the repository and stick it in your `/wp-content/themes/` directory. Run WordPress locally however you normally do, and activate the theme via the admin dashboard.

## Deployment

To deploy the theme, push to the `main` branch and the GitHub Actions workflow will deploy the theme to the live site. you will have to make sure your server has SSH enabled and that you generated a key on it for the actions script to use. details on that below:

### SSH Key

ssh into your server and generate a key with the following command:

```
ssh-keygen -t rsa -b 4096 -C "your_email@example.com" -f ~/.ssh/id_rsa -N ""
```

* use any key type you want, I'm showing RSA here
* you can use any email you want, it's just for your reference
* the key file should be created in your `.ssh` directory
* you will be prompted to enter a passphrase, just hit enter to skip it

Once you have the key, you can add it to the authorized keys file with the following command:

```
cat ~/.ssh/id_rsa.pub >> ~/.ssh/authorized_keys
```
Then find and copy your private key with this command:

```
cat ~/.ssh/id_rsa
```

Copy the entire output of the command:

```
-----BEGIN OPENSSH PRIVATE KEY-----
base64-encoded-key-data-goes-here
-----END OPENSSH PRIVATE KEY-----
```

Then you can add the private key to the GitHub Actions workflow by adding it to the `PRIVATE_KEY` secret in the repository settings.

## Manual Deployment

If you'd rather deploy the theme manually, just add your local machine's public key to the authorized keys file on your server and run the opposite rsync command from the deployment workflow:

```
rsync -avz --delete -e "ssh -p PORT" ./src/ USERNAME@HOST:REMOTE_PATH/wp-content/themes/twentytwentyfour-spenpo/
```
