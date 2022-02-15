# Deno Runtime 1.14

This is the Open Runtime that builds and runs Deno code based on a `deno:alpine-1.14.1` base image. 

The runtime itself uses [oak](https://deno.land/x/oak@v10.2.1) as the Web Server to process the execution requests.

To learn more about runtimes, visit [Runtimes introduction](https://github.com/open-runtimes/open-runtimes#runtimes-introduction) section of the main README.md.

## Usage

1. Create a folder and enter it. Add code into `mod.ts` file:

```bash
mkdir deno-or && cd deno-or
echo 'export default async function(req: any, res: any) { res.json({ n: Math.random() }) }' > mod.ts
```

2. Build the code:

```bash
ENTRYPOINT_NAME=mod.ts docker run --rm --interactive --tty --volume $PWD:/usr/code open-runtimes/deno:1.14 sh /usr/local/src/build.sh
```

3. Spin-up open-runtime:

```bash
docker run -p 3000:3000 -e INTERNAL_RUNTIME_KEY=secret-key --rm --interactive --tty --volume $PWD/code.tar.gz:/tmp/code.tar.gz:ro open-runtimes/deno:1.14 sh /usr/local/src/start.sh
```

4. In new terminal window, execute function:

```
curl -H "X-Internal-Challenge: secret-key" -H "Content-Type: application/json" -X POST http://localhost:3000/ -d '{"payload": {}}'
```

Output `{"n":0.7232589496628183}` with random float will be displayed after the execution.

## Local development

1. Clone the [open-runtimes](https://github.com/open-runtimes/open-runtimes) repository:

```bash
git clone https://github.com/open-runtimes/open-runtimes.git
```

2. Enter the deno runtime folder:

```bash
cd open-runtimes/runtimes/deno-1.14
```

3. Run the included example cloud function:

```bash
docker-compose up -d
```

4. Execute the function:

```bash
curl -H "X-Internal-Challenge: secret-key" -H "Content-Type: application/json" -X POST http://localhost:3000/ -d '{"payload": {}}'
```

You can now send `POST` request to `http://localhost:3000`. Make sure you have header `x-internal-challenge: secret-key`. If your function expects any parameters, you can pass an optional JSON body like so: `{ "payload":{} }`.

You can also make changes to the example code and apply the changes with the `docker-compose restart` command.

## Notes

- When writing functions for this runtime, ensure they are exported. An example of this is:

```js
export default async function(req: any, res: any) {
    res.send('Hello Open Runtimes 👋');
}
```

- The `res` parameter has two methods:

    - `send()`: Send a string response to the client.
    - `json()`: Send a JSON response to the client.

You can respond with `json()` by providing object:

```js
export default async function(req: any, res: any) {
    res.json({
        'message': 'Hello Open Runtimes 👋',
        'env': req.env,
        'payload': req.payload,
        'headers': req.headers
    });
}
```

- Dependencies are handeled automatically. Open Runtimes automatically cache and install them during build process.

- The default entrypoint is `mod.ts`. If your entrypoint differs, make sure to provide it in the JSON body of the request: `{"file":"src/app.ts"}`.


## Authors

**Eldad Fux**

+ [https://twitter.com/eldadfux](https://twitter.com/eldadfux)
+ [https://github.com/eldadfux](https://github.com/eldadfux)

**Bradley Schofield**

+ [https://github.com/PineappleIOnic](https://github.com/PineappleIOnic)

**Matej Bačo**

+ [https://github.com/Meldiron](https://github.com/Meldiron)

## Contributing

For security issues, please email security@appwrite.io instead of posting a public issue in GitHub.

You can refer to the [Contributing Guide](https://github.com/open-runtimes/open-runtimes/blob/main/CONTRIBUTING.md) for more info.