class ArticlePage
  include PageObject

  page_url URL.url('<%= params[:article_name] %><%= "?#{params[:query]}" if params[:query] %>')

  div(:banner_name, id: "centralnotice_testbanner_name")
end
