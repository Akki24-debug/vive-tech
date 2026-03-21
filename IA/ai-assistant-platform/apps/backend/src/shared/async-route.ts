import { NextFunction, Request, Response } from "express";

export type AsyncRouteHandler = (
  request: Request,
  response: Response,
  next: NextFunction
) => Promise<void>;

export function asyncRoute(handler: AsyncRouteHandler) {
  return (request: Request, response: Response, next: NextFunction) => {
    handler(request, response, next).catch(next);
  };
}
