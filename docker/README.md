
# DO NOT TRY AND RUN GIT WITHIN THE CONTAINER
(cause you can't)

## What is this?
The configuration needed for running the CGov Digital Platform in a docker compose stack. (As opposed to Vagrant, which has issues on Windows, and can be slow to recreate)

## Running your development stack

### Quick Reference
**Make sure your machine and project have been setup before starting**
* **STARTING:** Run `docker-compose up -d` within this directory (`docker`) to start up the stack.
* **STOPPING:** `docker-compose down` within this directory (`docker`) to start up the stack.

**NOTE:** Currently a `docker-compose down` blows away the database. This means every restart requires an [Initial Setup of Site](#Initial-Setup-of-Site).

### 1. Initial Setup of Your Machine
1. Install docker
1. Install PHP & Composer
1. (Mac only)Install dnsmasq - this will allow http://*.devbox to be routed to docker's "web" container.
   1. `brew install dnsmasq`
   1. `sudo echo 'address=/devbox/127.0.0.1' >> /usr/local/etc/dnsmasq.conf`
   1. `sudo mkdir -p /etc/resolver`
   1. `echo 'nameserver 127.0.0.1' | sudo tee /etc/resolver/devbox` to setup DNS for the sites
   1. `sudo brew services restart dnsmasq`

### 2. Initial setup of your project
1. Clone the project to a location on your hard drive, we will call this `<project_root>`
1. `cd <project_root>`
1. Run `composer install` this will install the PHP packages to run the site, and also used by the git pre-commit hook
   * Run `composer cgov-clean` to remove previously installed composer files.
1. Run `composer cgov-init` to initialize the sample project files. This currently does the following:
   * Copy the `<project_root>/docker/docker.env.sample` file to a file named `<project_root>/docker/docker.env`. `docker.env` will not be tracked. This is where the containers' local overrides & secrets are managed.
   * Copy the `<project_root>/blt/example.local.blt.yml` to `<project_root>/blt/local.blt.yml`. This will allow you to set an local dev overrides for BLT. When working in the docker stack, this also overrides the database host.
1. You will probably want to start things and install the site. So go to [Initial Setup of Site](#Initial-Setup-of-Site) to do that.

### 3. Initial Setup of Site
This is how you can install a site. NOTE: at some point we will have a real site, so
1. `cd <project_root>`
1. Start the stack by running `docker-compose -f docker/docker-compose.yml up -d`.
1. Run `docker-compose -f docker/docker-compose.yml exec web /bin/bash` to login to the web container
1. `cd /var/www`
1. `blt setup` -- Perform the initial site install.

The site is now accessible via [www.devbox](http://www.devbox)


**NOTE:** One more time, currently a `docker-compose down` blows away the database. This means every restart requires an [Initial Setup of Site](#Initial-Setup-of-Site).

### 4. What to do after checking out a new branch (or pulling updates for your current branch)
Run these commands locally:

1. `cd <project_root>`
1. `composer cgov-clean && composer install`
1. `docker-compose -f docker/docker-compose.yml build [--no-cache]`  (see note below about when to include `--no-cache`)
1. `docker-compose -f docker/docker-compose.yml up -d`
1. `docker-compose -f docker/docker-compose.yml exec web bash`

(Use the `--no-cache` option -- without the square brackets -- for step 3 if it has been a long time since you've worked on the project or if instructed to do so by another member of the team.)

Run these commands inside the docker container:

`blt --no-interaction cgov:reinstall && blt --no-interaction cgov:rebuild-feq`

### Managing Virtual Hosts
At some point in time there will be multiple websites within our project. (e.g. www & dceg) You can add additional virtual hosts to the apache configuration by:
1. Edit `docker.env`
1. Add the new host name (space separated) to the `APACHE_SITE_ALIAS` variable.
1. Save the file
1. Restart the stack

## File Structure
* **db** - Directory containing the files needed for the database container. See [db/README.md](db/README.md) for more information.
* **web** - Directory containing the file needed for the web/tooling container.  See [web/README.md](web/README.md) for more information.
* README.md - This file
* `docker-compose.yml` - The compose project file
* *docker-compose.override.yml* - A docker compose override file which allows you to locally override anything in the docker-compose.yml. This is primarily used for volume mounts. This file is untracked in source control. This is not really required unless you have been told to create it! See https://runnable.com/docker/advanced-docker-compose-configuration for a good discussion of the file.
* `docker-compose.override.yml.sample` - A sample override file.
* *docker.env* - A file used to store environment variables (usually secrets or local configuration) which is "pushed" into each container within the docker-compose file. This file is untracked in source control.
* `docker.env.sample` - A sample docker environment variables file.

## Software
* db
  * OS:
  * MariaDB
* web
  * OS: Ubuntu Bionic
  * PHP 7.4
    * bz2
    * cli
    * common
    * curl
    * fpm
    * gd
    * json
    * mbstring
    * memcached
    * mysql
    * oauth
    * opcache
    * readline
    * sqlite3
    * soap
    * xdebug
    * xml
  * Apache 2
  * Composer
  * Drush Launcher
  * curl
  * git
  * vim
  * MariaDB Client



## Running the stack with XDebug
XDebug is the PHP runtime debugger. While it helpful in stepping through code, it has major performance implications. So XDebug is not enabled by default. The other annoying thing about XDebug is that it must be configured for your specific IDE.

*This is still being worked on*
TODO:
1. Setup the xdebug.ini overrides (VSCode & PHPStorm)
1. Edit docker.env and set `XDEBUG_SAPI` to `apache2` in order to enable the XDEBUG module for apache. (If you *really* need to debug
  command line, then set it to `cli`.)
    1. Check /etc/php/7.2/apache2/conf.d/xdebug.ini
2. Setup the docker-compose.override.yml.sample
3. Test & document

## Rebuilding the Stack
You should not need to at this point. So this section is TBD.

## TODO:
1. Work on debugging
1. Add in memcached
1. Add instructions for rebuilding / force rebuilding
1. Create aliases for containered commands (blt, drush, composer?)
1. Make nice project name in composer so it is not just docker_svc_1
1. Avoid root
1. Configure settings to match env vars
