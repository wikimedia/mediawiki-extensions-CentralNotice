Given(/^I roll ([0-9.]+) for banner choice$/) do |pseudorandom|
  @override_random_banner = pseudorandom
end

When(/^I view an article$/) do
  params = { article_name: 'Special:Randompage' }
  params[:query] = "randombanner=#{@override_random_banner}" if @override_random_banner

  visit(ArticlePage, using_params: params)
end

Then(/^I see banner (\w+)$/) do |banner_name|
  expect(on(ArticlePage).banner_name).to match banner_name
end

Then(/^I see no banner$/) do
  expect(on(ArticlePage).banner_name_element).not_to be_visible
end
