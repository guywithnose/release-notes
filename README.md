# Release Notes builer tool

Look at the commits on a github repo and generate release notes using the
commits that have occurred since the last tag.

## Basic Usage

Release Notes requires that you specify the organization or username and the
repository name. It will then process commits to build release notes and a tag
on the specificed respository. You will be prompted several options to change
the output and determine how the tag will be generated.

```sh
$ bin/buildRelease organization-name repo-name
```

There are many command line argument options that can specified to allow for
the tool to run with little to no interaction from the user. All options can
be viewed by running the command with `--help`

```sh
$ bin/buildRelease --help
```
## Github Integration
Github integration is done using a personal access token. This token will be
requested when you run the tool the first time or you can provide the
information on the command-line using `--access-token` argument option.

If you provide your token at the prompt of the tool it will store this token
in a `.access_token` file. You can specify a different access token file with
the `--token-file` argument option. The default location of this file can be
changed with the `--cache-dir` argument option.

If you wish to use the tool with a differnt API version or a private GitHub
Enterprise server then you can use `--github-api` argument option to change the base URL the tool uses for making API calls.

## Jira Integration
Jira integration currently requires that you create a .env file that contains
the server url, username, and password that you are accessing jira as.

Start by copying .env.dist to .env and modifying with your information.

Using the `--jira-types` and `--jira-lookup` argument options will attempt to
find Jira issue numbers within the commit messages use for generating the notes
and then query the server.
