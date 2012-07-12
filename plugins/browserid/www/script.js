document.observe('dom:loaded', function () {
    function identity(assertion) {
        if (assertion) {
            // This code will be invoked once the user has
            // successfully selected an email address they
            // control to sign in with.
            checkAssertion(assertion);
        } else {
            // something went wrong! the user isn't logged in.
            loggedOut();
        }
     }

     function handleResponse(transport) {
         var json = transport.responseJSON;
         if (json) {
             if (json.realname) {
                 loggedIn(json.realname);
             } else if (json.choose_user) {
                 chooseUser(json.choose_user, json.assertion);
             } else if (json.redirect) {
                 window.location.href = json.redirect;
             }
         } else {
             loggedOut();
         }
     }

     function checkAssertion(assertion) {
        new Ajax.Request('/plugins/browserid/', {
            method: 'POST',
            parameters: { assertion: assertion },
            onSuccess: handleResponse,
            onFailure: function (transport) {
                alert('Login failure');
            }
        });
     }

     function chooseUser(users, assertion) {
         var choice = promptUsers(users, assertion);
         if (choice) {
            userMadeItsChoice(choice, assertion);
         }
     }

    function userMadeItsChoice(user_id, assertion) {
        new Ajax.Request('/plugins/browserid/', {
            method: 'POST',
            parameters: {
               assertion:    assertion,
               choosen_user: user_id
            },
            onSuccess: handleResponse,
            onFailure: function (transport) {
               alert('Login failure');
            }
        });
    }

    function promptUsers(users, assertion) {
        /*
        var names = [],
            choice = false;
        $A(users).each(function (user) {
                       console.log(user);
                       names.push(user.id+' '+user.realname);
                       });
        return prompt(names.join("\n"));
        */
        var alert = new Element('div').hide();
        alert.addClassName('modal fade in');
        alert.update('<div class="modal-header"> \
            <a class="close" data-dismiss="modal">×</a> \
            <h3>Please choose the user you want to login</h3> \
          </div> \
          <div class="modal-body"></div>');
        $A(users).each(
            function (user) {
                var p = new Element('p').update('<h4>'+user.realname+'</h4>');
                var button = new Element('button')
                    .update('Login as '+user.name)
                    .observe('click', function (evt) {
                        userMadeItsChoice(user.id, assertion);
                        jQuery(alert).modal('hide');
                        Evt.stop(evt);
                        return false;
                    });
                p.insert(button);
                alert.down('.modal-body').insert(p);
            }
        );
        jQuery(alert).modal();
    }

     function loggedIn(realname) {
         //alert('Welcome '+realname);
         window.location.reload();
     }

     function loggedOut() {
         alert('You are logged out');
     }

     $$('a[href="/account/login.php"]').each(function (login_button) {
         login_button.observe('click', function (evt) {
            navigator.id.get(identity);
            Event.stop(evt);
            return false;
         });
     });
});
