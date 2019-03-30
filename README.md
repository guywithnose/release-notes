# Release Notes builer tool

Look at the commits on a github repo and generate release notes using the commits
that have occurred since the last tag.

## Github Integration
Github integration is done using a personal access token. This token will be requested
when you run the tool the first time or you can provide the information on the command-line
using `--access-token` argument option.

## Jira Integration
Jira integration currently requires that you create a .env file that contains the
server url, username, and password that you are accessing jira as.

Start by copying .env.dist to .env and modifying with your information.

Using the `--jira-types` and `--jira-lookup` argument options will attempt to find
Jira issue numbers within the commit messages use for generating the notes and then
query the server.
