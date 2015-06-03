[![Build Status](https://api.travis-ci.org/rubberpants/Rocket.svg?branch=1.0)](http://travis-ci.org/rubberpants/Rocket)

# Rocket
Redis PHP Job Queue

## Major Features ##

- Dynamic queue creation with JQ routing rules
- Schedule jobs to queue in the future
- Park/Unpark waiting jobs
- Progress/Pausing/Resuming running jobs supported
- Limits on number of jobs waiting in a queue
- Dynamic running job concurrency limits per queue based on overall system utilization
- Statistics collection overall and per queue
- History of all events per job
- Move jobs between queues
- Monitoring of job state. Alerts if a job remains in a specific state for too long
- Automatic requeue of job when delivery to worker is not confirmed after a specified time limit
- Optional detection of duplicate jobs
- Extensible event-based plugin architecture
- Command-line example implementation

