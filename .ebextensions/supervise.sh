#!/bin/bash
if [ "${SUPERVISE}" == "enable" ]; then

export HOME="/root"
export PATH="/sbin:/bin:/usr/sbin:/usr/bin:/opt/aws/bin"

easy_install supervisor

cat <<'EOB' > /etc/init.d/supervisord

    # Source function library
    . /etc/rc.d/init.d/functions

    # Source system settings
    if [ -f /etc/sysconfig/supervisord ]; then
        . /etc/sysconfig/supervisord
    fi

    # Path to the supervisorctl script, server binary,
    # and short-form for messages.
    supervisorctl=${SUPERVISORCTL-/usr/bin/supervisorctl}
    supervisord=${SUPERVISORD-/usr/bin/supervisord}
    prog=supervisord
    pidfile=${PIDFILE-/var/run/supervisord.pid}
    lockfile=${LOCKFILE-/var/lock/subsys/supervisord}
    STOP_TIMEOUT=${STOP_TIMEOUT-60}
    OPTIONS="${OPTIONS--c /etc/supervisord.conf}"
    RETVAL=0

    start() {
        echo -n $"Starting $prog: "
        daemon --pidfile=${pidfile} $supervisord $OPTIONS
        RETVAL=$?
        echo
        if [ $RETVAL -eq 0 ]; then
            touch ${lockfile}
            $supervisorctl $OPTIONS status
        fi
        return $RETVAL
    }

    stop() {
        echo -n $"Stopping $prog: "
        killproc -p ${pidfile} -d ${STOP_TIMEOUT} $supervisord
        RETVAL=$?
        echo
        [ $RETVAL -eq 0 ] && rm -rf ${lockfile} ${pidfile}
    }

    reload() {
        echo -n $"Reloading $prog: "
        LSB=1 killproc -p $pidfile $supervisord -HUP
        RETVAL=$?
        echo
        if [ $RETVAL -eq 7 ]; then
            failure $"$prog reload"
        else
            $supervisorctl $OPTIONS status
        fi
    }

    restart() {
        stop
        start
    }

    case "$1" in
        start)
            start
            ;;
        stop)
            stop
            ;;
        status)
            status -p ${pidfile} $supervisord
            RETVAL=$?
            [ $RETVAL -eq 0 ] && $supervisorctl $OPTIONS status
            ;;
        restart)
            restart
            ;;
        condrestart|try-restart)
            if status -p ${pidfile} $supervisord >&/dev/null; then
              stop
              start
            fi
            ;;
        force-reload|reload)
            reload
            ;;
        *)
            echo $"Usage: $prog {start|stop|restart|condrestart|try-restart|force-reload|reload}"
            RETVAL=2
    esac

    exit $RETVAL
    EOB

      chmod +x /etc/init.d/supervisord

      cat <<'EOB' > /etc/sysconfig/supervisord
    # Configuration file for the supervisord service
    #
    # Author: Jason Koppe <jkoppe@indeed.com>
    #             orginal work
    #         Erwan Queffelec <erwan.queffelec@gmail.com>
    #             adjusted to new LSB-compliant init script

    # make sure elasticbeanstalk PARAMS are being passed through to supervisord
    . /opt/elasticbeanstalk/support/envvars

    # WARNING: change these wisely! for instance, adding -d, --nodaemon
    # here will lead to a very undesirable (blocking) behavior
    #OPTIONS="-c /etc/supervisord.conf"
    PIDFILE=/var/run/supervisord/supervisord.pid
    #LOCKFILE=/var/lock/subsys/supervisord.pid

    # Path to the supervisord binary
    SUPERVISORD=/usr/local/bin/supervisord

    # Path to the supervisorctl binary
    SUPERVISORCTL=/usr/local/bin/supervisorctl

    # How long should we wait before forcefully killing the supervisord process ?
    #STOP_TIMEOUT=60

    # Remove this if you manage number of open files in some other fashion
    #ulimit -n 96000
    EOB

      mkdir -p /var/run/supervisord/
      chown webapp: /var/run/supervisord/

      cat <<'EOB' > /etc/supervisord.conf
    [unix_http_server]
    file=/tmp/supervisor.sock
    chmod=0777

    [supervisord]
    logfile=/var/app/support/logs/supervisord.log
    logfile_maxbytes=0
    logfile_backups=0
    loglevel=warn
    pidfile=/var/run/supervisord/supervisord.pid
    nodaemon=false
    nocleanup=true
    user=webapp

    [rpcinterface:supervisor]
    supervisor.rpcinterface_factory = supervisor.rpcinterface:make_main_rpcinterface

    [supervisorctl]
    serverurl=unix:///tmp/supervisor.sock

    [program:laravel-worker]
    process_name=%(program_name)s_%(process_num)02d
    command=php /var/app/current/artisan queue:work --tries=1 --sleep=2
    autostart=true
    autorestart=true
    user=root
    numprocs=5
    redirect_stderr=true
    stdout_logfile=/var/app/current/storage/worker.log

    EOB

      # this is now a little tricky, not officially documented, so might break but it is the cleanest solution
      # first before the "flip" is done (e.g. switch between ondeck vs current) lets stop supervisord
      echo -e '#!/usr/bin/env bash\nservice supervisord stop' > /opt/elasticbeanstalk/hooks/appdeploy/enact/00_stop_supervisord.sh
      chmod +x /opt/elasticbeanstalk/hooks/appdeploy/enact/00_stop_supervisord.sh
      # then right after the webserver is reloaded, we can start supervisord again
      echo -e '#!/usr/bin/env bash\nservice supervisord start' > /opt/elasticbeanstalk/hooks/appdeploy/enact/99_z_start_supervisord.sh
      chmod +x /opt/elasticbeanstalk/hooks/appdeploy/enact/99_z_start_supervisord.sh
  fi