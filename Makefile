APP_CONTAINER = lumished-local-app-1

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
	set -a; source ./deploy.local.sh; set +a; ./deploy.sh deploy

deploy\:fresh:
	set -a; source ./deploy.local.sh; set +a; ./deploy.sh deploy

deploy\:start:
	set -a; source ./deploy.local.sh; set +a; ./deploy.sh start

deploy\:stop:
	set -a; source ./deploy.local.sh; set +a; ./deploy.sh stop

deploy\:restart:
	set -a; source ./deploy.local.sh; set +a; ./deploy.sh restart

deploy\:health:
	set -a; source ./deploy.local.sh; set +a; ./deploy.sh health
