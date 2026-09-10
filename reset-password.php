{
  "$schema": "https://openapi.vercel.sh/vercel.json",
  "name": "loc-voi",
  "framework": null,

  "functions": {
    "api/index.php": {
      "runtime": "vercel-php@0.9.0",
      "memory": 1024,
      "maxDuration": 30
    }
  },

  "routes": [
    {
      "src": "/assets/(.*)",
      "dest": "/assets/$1"
    },
    {
      "src": "/uploads/(.*)",
      "dest": "/uploads/$1"
    },
    {
      "src": "/(.*)",
      "dest": "/api/index.php"
    }
  ],

  "headers": [
    {
      "source": "/assets/(.*)",
      "headers": [
        {
          "key": "Cache-Control",
          "value": "public, max-age=86400"
        }
      ]
    },
    {
      "source": "/(.*)",
      "headers": [
        {
          "key": "X-Content-Type-Options",
          "value": "nosniff"
        },
        {
          "key": "X-Frame-Options",
          "value": "SAMEORIGIN"
        },
        {
          "key": "Referrer-Policy",
          "value": "strict-origin-when-cross-origin"
        }
      ]
    }
  ]
}
