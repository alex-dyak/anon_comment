While Drupal provides capabilities for commentng for both anonymous and authenicated users, 
it provides no easy way for authenticated users to post comments anonymously without having to manually log out first. 
This is not only inconvenient to users; it also poses a problem on sites that may choose to implement a "paywall" or 
other means of limiting access to content but wish to allow for anonymous commenting.

This module adds a "Post anonymously" checkbox to the default comment form in Drupal, for authenticated users granted permission 
to make use of this module's functionality. Comments posted when this box is checked will be stored in the comments database as 
anonymous comments, and displayed as such.

To prevent abuse, however, this module also implements an administration tab with the Comment admin that allows administrators 
(with sufficient privileges) to view a list of anonymous comments created by this module. These "anonymized" comments can 
then be unpublished or deleted like regular comments, or even converted back to "regular" comments with the author's 
name attached. Thus, it is important to note that comments made with this module are not truly anonymous as administrators 
can view original authors.
