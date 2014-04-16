(function($){
  function updateSelectBoxes() {
    if (localStorage.owners) {
      var owners = JSON.parse(localStorage.owners);
      for (var i in owners) {
          $('#owners').append('<option>' + owners[i] + '</option>');
      }

      $('#owners').show();
    }

    if (localStorage.repos) {
      var repos = JSON.parse(localStorage.repos);
      for (var i in repos) {
          $('#repos').append('<option>' + repos[i] + '</option>');
      }

      $('#repos').show();
    }
  }

  function addFavorites(newOwner, newRepo) {
      var owners;
      if (localStorage.owners) {
        owners = JSON.parse(localStorage.owners);
      } else {
        owners = [];
      }

      if (owners.indexOf(newOwner) == -1) {
        owners.push(newOwner);
      }

      localStorage.owners = JSON.stringify(owners);

      var repos;
      if (localStorage.repos) {
        repos = JSON.parse(localStorage.repos);
      } else {
        repos = [];
      }

      if (repos.indexOf(newRepo) == -1) {
        repos.push(newRepo);
      }

      localStorage.repos = JSON.stringify(repos);

      updateSelectBoxes();
  }

  function buildReleaseNotes(owner, repo) {
     $.ajax({
       url: 'https://api.github.com/repos/' + owner + '/' + repo + '/releases',
       dataType: 'json',
       headers: {Authorization: 'token ' + localStorage.github_access_token},
       success: function(releases) {
         var tagName = releases[0].tag_name;
         $.ajax({
           url: 'https://api.github.com/repos/' + owner + '/' + repo + '/compare/' + tagName + '...master',
           dataType: 'json',
           headers: {Authorization: 'token ' + localStorage.github_access_token},
           success: function(response) {
             var releaseNotes = '';
             for (var i in response.commits) {
               var commit = response.commits[i];
               if (commit.parents.length == 2) {
                 var messageData = commit.commit.message.match(/Merge pull request #([0-9]*)[^\n]*\n[^\n]*\n(.*)/);
                 var prNum = messageData[1];
                 var message = messageData[2];
                 releaseNotes += '* ' + message + '&nbsp;<sup>[PR&nbsp;#' + prNum + ']\n';
               }
             }

             nextTagName = getNextTagName(tagName);
             var nextVersionNumber = nextTagName.replace(/[^0-9\.]/, '');
             var randomName = generate_game_name();
             var postData = {
               'tag_name': nextTagName,
               'name': 'Version ' + nextVersionNumber + ' -- ' + randomName,
               'body': $.trim(releaseNotes),
               'draft': true
             };

             $.ajax({
               url: 'https://api.github.com/repos/' + owner + '/' + repo + '/releases',
               data: JSON.stringify(postData),
               type: 'POST',
               headers: {Authorization: 'token ' + localStorage.github_access_token},
               success: function(response) {
                 location = response.html_url;
               }
             });
           }
         });
       }
     });
  }

  function getNextTagName(tagName) {
    var parts = tagName.split('.');
    parts[parts.length - 1]++;
    return parts.join('.');
  }

  function init() {
    $.get('vgng/video_game_names.txt', function(data) {
      build_list(data);
      $("div#video_game_name0").text("Click below to generate a video game name");
    });

    if (localStorage.github_access_token) {
      $('#pickRepo').show();
    } else {
      $('#getAccessToken').show();
    }

    updateSelectBoxes();

    $('#saveAccessToken').click(function() {
      localStorage.github_access_token = $('#accessToken').val();
      $('#getAccessToken').hide();
      $('#pickRepo').show();
    });

    $('#buildReleaseNotes').click(function() {
      var owner = $('#owner').val();
      var repo = $('#repoName').val();

      addFavorites(owner, repo);

      buildReleaseNotes(owner, repo);
    });

    $('#owners').change(function(){
      $('#owner').val($(this).val());
    });

    $('#repos').change(function(){
      $('#repoName').val($(this).val());
    });
  }

  $(init);
}(jQuery))
