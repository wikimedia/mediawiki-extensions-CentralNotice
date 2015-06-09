# Article page, potentially has a CentralNotice overlay
#
# Supports query parameters, which are used to override default
# banner controller behaviors.
class ArticlePage
  include PageObject

  url_template = '<%= params[:article_name] %>' \
    '<%= "?#{params[:query]}" if params[:query] %>'
  page_url url_template

  div(:banner_name, id: 'centralnotice_testbanner_name')
end
