APP_CONTAINER = local-app

TEST_ENV = -e APP_ENV=testing -e DB_CONNECTION=sqlite -e DB_DATABASE=database/testing.sqlite

test:
	docker exec -i $(APP_CONTAINER) php artisan config:clear
	docker exec -i $(TEST_ENV) $(APP_CONTAINER) php artisan test --parallel

test-filter:
	docker exec -i $(TEST_ENV) $(APP_CONTAINER) php artisan test --filter=$(filter)

test-file:
	docker exec -i $(TEST_ENV) $(APP_CONTAINER) php artisan test $(file)

artisan:
	docker exec -i $(APP_CONTAINER) php artisan $(cmd)

deploy:
	. ./devops/scripts/deploy.local.env.sh && ./devops/scripts/deploy.sh
